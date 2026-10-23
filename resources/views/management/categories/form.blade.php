@extends('layouts.portal')
@section('title', $category->exists ? 'Edit kategori' : 'Tambah kategori')
@section('content')
<div class="page-heading"><div><p class="section-label">MASTER DATA</p><h1>{{ $category->exists ? 'Edit kategori' : 'Tambah kategori' }}</h1><p>Gunakan nama kategori yang ringkas dan mudah dikenali.</p></div><a href="{{ route('categories.index') }}" class="button button-secondary">Kembali</a></div>
<form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="editor-panel">@csrf @if($category->exists) @method('PUT') @endif
<div class="form-field"><label for="name">Nama kategori <span>*</span></label><input id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="120" placeholder="Contoh: Audio & Earbuds"></div>
<div class="form-field"><label for="description">Deskripsi</label><textarea id="description" name="description" rows="4" maxlength="1000">{{ old('description', $category->description) }}</textarea></div>
<div class="editor-actions"><a href="{{ route('categories.index') }}" class="button button-secondary">Batal</a><button class="button button-primary">Simpan kategori</button></div></form>
@endsection
