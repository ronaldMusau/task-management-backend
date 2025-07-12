<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'deadline' => 'required|date|after:today',
            'assigned_to' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'deadline' => $request->deadline,
            'assigned_to' => $request->assigned_to,
            'created_by' => auth()->id(),
            'status' => 'pending'
        ]);

        $task->load(['assignee', 'creator']);

        // Send notification to assigned user
        $task->assignee->notify(new TaskAssigned($task));

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'task' => $task
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

        // Notify task creator about status change
        $task->creator->notify(new TaskStatusChanged($task));

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'task' => $task
        ]);
    }
}