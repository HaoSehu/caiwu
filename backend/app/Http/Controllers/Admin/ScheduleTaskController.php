<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Automation\ScheduleTaskService;
use App\Services\Automation\ScheduleTaskTriggerService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ScheduleTaskController extends Controller
{
    public function __construct(
        private ScheduleTaskService $scheduleTaskService,
        private ScheduleTaskTriggerService $scheduleTaskTriggerService,
    ) {}

    public function overview()
    {
        return $this->success($this->scheduleTaskService->overview());
    }

    public function trigger(Request $request)
    {
        $validated = $request->validate([
            'task' => ['required', 'string'],
        ]);

        try {
            $result = $this->scheduleTaskTriggerService->dispatch(
                (string) $validated['task'],
                $request->user()?->id ? (int) $request->user()->id : null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error(42200, $exception->getMessage());
        }

        return $this->success($result);
    }
}
