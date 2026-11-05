<?php

namespace App\Features\Ads\Http\Controllers\Api\V1;

use App\Application\Actions\Ads\ClaimAdShareTask;
use App\Application\Actions\Ads\SubmitAdShareEvidence;
use App\Models\Ad;
use App\Models\AdShareTask;
use App\Models\AdShareEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdShareController
{
    public function claim(Request $request, Ad $ad, ClaimAdShareTask $claim): JsonResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:100']]);
        $task = $claim->handle($request->user(), $ad, $data['idempotency_key']);

        return response()->json(['data' => $task], $task->wasRecentlyCreated ? 201 : 200);
    }

    public function submit(Request $request, AdShareTask $task, SubmitAdShareEvidence $submit): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', 'max:48'],
            'post_url' => ['required', 'url', 'max:191'],
            'screenshot' => ['nullable', 'image', 'max:10240'],
        ]);
        $screenshotPath = isset($data['screenshot'])
            ? $data['screenshot']->store('ad-share-evidence/'.$request->user()->id)
            : null;
        $event = $submit->handle($request->user(), $task, $data['channel'], $data['post_url'], $screenshotPath);

        return response()->json(['data' => $event, 'message' => 'Share evidence submitted for admin verification.'], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => AdShareTask::query()->with(['ad', 'events'])
            ->where('user_id', $request->user()->id)->latest('claimed_at')->paginate(30)]);
    }

    public function events(Request $request): JsonResponse
    {
        return response()->json(['data' => AdShareEvent::query()->with(['task.ad', 'moderationDecisions'])
            ->whereHas('task', fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest('shared_at')->paginate(30)]);
    }
}
