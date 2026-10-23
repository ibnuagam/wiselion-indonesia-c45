@extends('layouts.portal')
@section('title', 'Edit warna '.$color->name)
@section('content')
<div class="page-heading"><div><p class="section-label">PRODUCT COLORS</p><h1>Edit warna {{ $color->name }}</h1><p>{{ $product->name }} · {{ $product->code }}</p></div><a href="{{ route('products.colors.index', $product) }}" class="button button-secondary">Semua warna</a></div>
<form method="POST" action="{{ route('products.colors.update', [$product, $color]) }}" enctype="multipart/form-data" class="editor-panel color-edit-panel">@csrf @method('PUT') @include('management.colors.fields')</form>
@endsection
