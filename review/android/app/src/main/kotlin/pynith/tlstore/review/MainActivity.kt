package pynith.tlstore.review

import android.content.Intent
import android.net.Uri
import android.util.Patterns
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        intent = normalizeSelectedText(intent)
        super.onCreate(savedInstanceState)
    }

    override fun onNewIntent(intent: Intent) {
        val normalizedIntent = normalizeSelectedText(intent)
        super.onNewIntent(normalizedIntent)
        setIntent(normalizedIntent)
    }

    private fun normalizeSelectedText(source: Intent): Intent {
        val uri = when (source.action) {
            Intent.ACTION_PROCESS_TEXT -> {
                val text = source.getCharSequenceExtra(Intent.EXTRA_PROCESS_TEXT)?.toString()?.trim()
                    ?: return source
                val match = Patterns.WEB_URL.matcher(text).let { matcher ->
                    if (matcher.find()) matcher.group() else null
                }?.trimEnd('.', ',', ';', ':', '!', '?', ')', ']', '}', '\'', '"')
                    ?.takeIf(String::isNotBlank)
                    ?: return source
                val link = if (match.startsWith("http://", true) ||
                    match.startsWith("https://", true)
                ) match else "https://$match"
                runCatching { Uri.parse(link) }.getOrNull() ?: return source
            }
            Intent.ACTION_VIEW -> source.data ?: return source
            else -> return source
        }

        if (uri.scheme?.lowercase() !in setOf("http", "https") ||
            uri.host?.lowercase() !in appWebsiteHosts
        ) return source

        val isRegistration = uri.pathSegments.any { it.equals("register", ignoreCase = true) }
        val internalUri = Uri.Builder()
            .scheme("tlstore")
            .authority(if (isRegistration) "register" else "open")
            .apply {
                if (isRegistration) {
                    val code = uri.getQueryParameter("ref")
                        ?: uri.getQueryParameter("referral_code")
                        ?: uri.getQueryParameter("referral")
                    if (!code.isNullOrBlank()) appendQueryParameter("ref", code)
                }
            }
            .build()

        return Intent(Intent.ACTION_VIEW, internalUri).apply {
            addCategory(Intent.CATEGORY_BROWSABLE)
        }
    }

    private companion object {
        val appWebsiteHosts = setOf(
            "gptminpro.online",
            "tlstore.org",
            "app.tlstore.org",
            "10.145.107.216",
        )
    }
}
