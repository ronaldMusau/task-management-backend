<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusChanged extends Notification
{
    protected $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $actionText = $notifiable->id === $this->task->creator->id
            ? 'View All Tasks'
            : 'View Task';

        return (new MailMessage)
            ->subject('Task Status Updated: ' . $this->task->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Task status has been updated:')
            ->line('**Task:** ' . $this->task->title)
            ->line('**Updated by:** ' . $this->task->assignee->name)
            ->line('**New Status:** ' . ucfirst(str_replace('_', ' ', $this->task->status)))
            ->line('**Deadline:** ' . $this->task->deadline->format('M d, Y H:i'))
            ->action($actionText, url('/api/tasks'))
            ->line('Thank you for using our task management system!');
    }

    public function toArray($notifiable)
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'status' => $this->task->status,
        ];
    }
}