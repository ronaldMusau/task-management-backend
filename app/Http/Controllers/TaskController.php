<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $tasks = Task::with(['assignee', 'creator'])->get();
        } else {
            $tasks = Task::with(['assignee', 'creator'])
                ->where('assigned_to', $user->id)
                ->get();
        }

        return response()->json([
            'success' => true,
            'tasks' => $tasks
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'deadlines' => 'required|array',
            'deadlines.*' => 'required|date|after:today',
            'assigned_to' => 'required|array',
            'assigned_to.*' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $tasks = [];
        $creatorId = auth()->id();

        foreach ($request->assigned_to as $index => $email) {
            $user = User::where('email', $email)->first();
            $deadline = $request->deadlines[$index] ?? $request->deadlines[0];

            $task = Task::create([
                'title' => $request->title,
                'description' => $request->description,
                'deadline' => $deadline,
                'assigned_to' => $user->id,
                'created_by' => $creatorId,
                'status' => 'pending'
            ]);

            $task->load(['assignee', 'creator']);

            try {
                Notification::send($task->assignee, new TaskAssigned($task));
                Log::info("Task assignment notification sent to {$task->assignee->email}");
            } catch (\Exception $e) {
                Log::error("Failed to send notification for task {$task->id}: " . $e->getMessage());
            }

            $tasks[] = $task;
        }

        return response()->json([
            'success' => true,
            'message' => count($tasks) . ' task(s) created successfully',
            'tasks' => $tasks
        ], 201);
    }

    public function bulkAssign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'deadline' => 'required|date|after:today',
            'assign_to_all' => 'required|boolean',
            'emails' => 'required_if:assign_to_all,false|array',
            'emails.*' => 'email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $users = $request->assign_to_all
            ? User::where('role', 'user')->get()
            : User::whereIn('email', $request->emails ?? [])->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No users found to assign tasks to'
            ], 400);
        }

        $tasks = [];
        $creatorId = auth()->id();

        foreach ($users as $user) {
            $task = Task::create([
                'title' => $request->title,
                'description' => $request->description,
                'deadline' => $request->deadline,
                'assigned_to' => $user->id,
                'created_by' => $creatorId,
                'status' => 'pending'
            ]);

            $task->load(['assignee', 'creator']);

            try {
                Notification::send($task->assignee, new TaskAssigned($task));
                Log::info("Bulk task assignment notification sent to {$task->assignee->email}");
            } catch (\Exception $e) {
                Log::error("Failed to send bulk notification for task {$task->id}: " . $e->getMessage());
            }

            $tasks[] = $task;
        }

        return response()->json([
            'success' => true,
            'message' => 'Tasks assigned successfully',
            'tasks_created' => count($tasks)
        ], 201);
    }


    public function show(Task $task)
    {
        $user = auth()->user();

        if (!$user->isAdmin() && $task->assigned_to !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view tasks assigned to you'
            ], 403);
        }

        $task->load(['assignee', 'creator']);

        return response()->json([
            'success' => true,
            'task' => $task
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'deadline' => 'sometimes|date|after:today',
            'assigned_to' => 'sometimes|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $task->update($request->all());
        $task->load(['assignee', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'task' => $task
        ]);
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully'
        ]);
    }

    public function updateStatus(Request $request, Task $task)
    {
        $user = auth()->user();

        if ($task->assigned_to !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update status of tasks assigned to you'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $task->update(['status' => $request->status]);
        $task->load(['assignee', 'creator']);

        try {
            Notification::send($task->creator, new TaskStatusChanged($task));
            Notification::send($task->assignee, new TaskStatusChanged($task));
            Log::info("Status change notifications sent for task {$task->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send status change notifications for task {$task->id}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'task' => $task
        ]);
    }

}