<h1>{{ $headline }}</h1>

<p>Task: {{ $task->title }}</p>

@if ($task->description)
    <p>{{ $task->description }}</p>
@endif

<dl>
    <dt>Status</dt>
    <dd>{{ str_replace('_', ' ', $task->status) }}</dd>
    <dt>Priority</dt>
    <dd>{{ $task->priority }}</dd>
    @if ($task->due_at)
        <dt>Due</dt>
        <dd>{{ $task->due_at->toDayDateTimeString() }}</dd>
    @endif
</dl>
