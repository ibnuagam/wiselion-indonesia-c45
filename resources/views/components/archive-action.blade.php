@props(['record', 'resource'])
@if($record->trashed())
<form method="POST" action="{{ route($resource.'.restore', $record) }}">@csrf @method('PATCH')<button type="submit" class="text-button">Pulihkan</button></form>
@else
<a class="text-button" href="{{ route($resource.'.edit', $record) }}">Edit</a><form method="POST" action="{{ route($resource.'.destroy', $record) }}" data-confirm="Pindahkan data ini ke arsip? Data dapat dipulihkan kembali.">@csrf @method('DELETE')<button type="submit" class="text-button text-danger">Arsipkan</button></form>
@endif
