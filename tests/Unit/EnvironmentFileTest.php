<?php

namespace Tests\Unit;

use App\Application\Settings\EnvironmentFile;
use App\Application\Settings\EnvironmentFileException;
use Dotenv\Exception\InvalidFileException;
use Tests\TestCase;

/**
 * The .env writer must never corrupt a deployment's file: comments, ordering
 * and unrelated variables survive, values are quoted the way dotenv expects,
 * and a missing or read-only file fails loudly instead of silently.
 */
class EnvironmentFileTest extends TestCase
{
    private string $path;

    private EnvironmentFile $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/tlstore-envfile-'.bin2hex(random_bytes(6)).'.env';
        file_put_contents($this->path, <<<'ENV'
        APP_NAME=TLStore
        APP_KEY=base64:abc=
        EMPTY_KEY=
        NULL_KEY=null

        # Provider credentials
        KORAPAY_SECRET_KEY=old-secret
        QUOTED="hello world"

        ENV);

        $this->file = new EnvironmentFile($this->path);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path.'.bak');

        parent::tearDown();
    }

    public function test_it_parses_values_like_laravel_does(): void
    {
        $values = $this->file->values();

        $this->assertSame('TLStore', $values['APP_NAME']);
        $this->assertSame('base64:abc=', $values['APP_KEY']);
        $this->assertSame('hello world', $values['QUOTED']);
        // `KEY=` and `KEY=null` mean "unset" to Laravel, so they read as null.
        $this->assertNull($values['EMPTY_KEY']);
        $this->assertNull($values['NULL_KEY']);
        $this->assertSame('old-secret', $this->file->get('KORAPAY_SECRET_KEY'));
        $this->assertNull($this->file->get('DOES_NOT_EXIST'));
    }

    public function test_it_replaces_values_in_place_and_appends_new_ones(): void
    {
        $changed = $this->file->put([
            'KORAPAY_SECRET_KEY' => 'new-secret',
            'OPENAI_API_KEY' => 'sk-new',
        ]);

        $this->assertSame(['KORAPAY_SECRET_KEY', 'OPENAI_API_KEY'], $changed);

        $contents = (string) file_get_contents($this->path);
        $this->assertStringContainsString('KORAPAY_SECRET_KEY=new-secret', $contents);
        $this->assertSame(1, substr_count($contents, 'KORAPAY_SECRET_KEY='));
        $this->assertStringContainsString("# Provider credentials\n", $contents);
        $this->assertStringContainsString('APP_NAME=TLStore', $contents);
        $this->assertStringContainsString('QUOTED="hello world"', $contents);
        $this->assertStringEndsWith("OPENAI_API_KEY=sk-new\n", $contents);

        // Order is preserved: the replaced line stays where it was.
        $this->assertLessThan(
            strpos($contents, 'KORAPAY_SECRET_KEY=new-secret'),
            strpos($contents, 'APP_KEY=base64:abc='),
        );
    }

    public function test_it_removes_lines_and_ignores_absent_keys(): void
    {
        $changed = $this->file->put([
            'KORAPAY_SECRET_KEY' => null,
            'NEVER_EXISTED' => null,
        ]);

        $this->assertSame(['KORAPAY_SECRET_KEY'], $changed);

        $contents = (string) file_get_contents($this->path);
        $this->assertStringNotContainsString('KORAPAY_SECRET_KEY', $contents);
        $this->assertStringNotContainsString('NEVER_EXISTED', $contents);
        // The comment that introduced the group survives the rewrite.
        $this->assertStringContainsString("# Provider credentials", $contents);
        $this->assertStringContainsString('APP_NAME=TLStore', $contents);
    }

    public function test_values_are_quoted_only_when_they_need_to_be(): void
    {
        $this->assertSame('simple-value', $this->file->format('simple-value'));
        $this->assertSame('https://api.example.com/v1', $this->file->format('https://api.example.com/v1'));
        $this->assertSame('AB:CD:EF,12:34', $this->file->format('AB:CD:EF,12:34'));
        $this->assertSame('', $this->file->format(''));
        $this->assertSame('"has space"', $this->file->format('has space'));
        $this->assertSame('"hash # inside"', $this->file->format('hash # inside'));
        $this->assertSame('"dollar \\$sign"', $this->file->format('dollar $sign'));
        $this->assertSame('"it\'s"', $this->file->format("it's"));
        $this->assertSame('"back\\\\slash"', $this->file->format('back\\slash'));
    }

    public function test_awkward_values_round_trip_through_the_file(): void
    {
        $this->file->put([
            'MAILGUN_SMTP_PASSWORD' => 'p@ss w#rd "quoted" \\ backslash',
            'SUPPORT_EMAIL' => 'help@tlstore.test',
        ]);

        $values = $this->file->values();

        $this->assertSame('p@ss w#rd "quoted" \\ backslash', $values['MAILGUN_SMTP_PASSWORD']);
        $this->assertSame('help@tlstore.test', $values['SUPPORT_EMAIL']);
    }

    public function test_quotes_backslashes_and_interpolation_markers_round_trip(): void
    {
        $this->file->put([
            'SECRET_ONE' => "it's",
            'SECRET_TWO' => 'C:\\path\\to\\',
            'SECRET_THREE' => '${HOME} literal',
            'SECRET_FOUR' => 'a=b&c=d',
        ]);

        $values = $this->file->values();

        $this->assertSame("it's", $values['SECRET_ONE']);
        $this->assertSame('C:\\path\\to\\', $values['SECRET_TWO']);
        $this->assertSame('${HOME} literal', $values['SECRET_THREE']);
        $this->assertSame('a=b&c=d', $values['SECRET_FOUR']);
    }

    public function test_a_no_op_write_leaves_the_file_byte_identical(): void
    {
        $before = (string) file_get_contents($this->path);

        $this->assertSame([], $this->file->put([]));
        $this->assertSame($before, (string) file_get_contents($this->path));
    }

    public function test_it_keeps_a_backup_of_the_previous_file(): void
    {
        $this->file->put(['KORAPAY_SECRET_KEY' => 'rotated']);

        $this->assertFileExists($this->path.'.bak');
        $this->assertStringContainsString('KORAPAY_SECRET_KEY=old-secret', (string) file_get_contents($this->path.'.bak'));
    }

    public function test_it_refuses_to_create_a_missing_file(): void
    {
        $missing = new EnvironmentFile($this->path.'.nope');

        $this->assertFalse($missing->exists());
        $this->assertSame([], $missing->values());

        $this->expectException(EnvironmentFileException::class);
        $missing->put(['APP_NAME' => 'New']);
    }

    public function test_it_refuses_to_write_a_read_only_file(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('Running as root: file permissions are not enforced.');
        }

        chmod($this->path, 0444);

        try {
            $this->assertFalse($this->file->isWritable());

            $this->expectException(EnvironmentFileException::class);
            $this->file->put(['APP_NAME' => 'Changed']);
        } finally {
            chmod($this->path, 0644);
        }
    }

    public function test_an_unparseable_file_throws_instead_of_guessing(): void
    {
        file_put_contents($this->path, "APP_NAME=ok\nTHIS LINE IS BROKEN\n");

        $this->expectException(InvalidFileException::class);
        $this->file->values();
    }
}
