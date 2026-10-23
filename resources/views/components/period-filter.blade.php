@props(['products', 'categories'])
<div class="filter-field"><label for="filter-start">Periode awal</label><input id="filter-start" type="month" name="start" value="{{ request('start') }}"></div>
<div class="filter-field"><label for="filter-end">Periode akhir</label><input id="filter-end" type="month" name="end" value="{{ request('end') }}"></div>
<div class="filter-field"><label for="filter-category">Kategori</label><select id="filter-category" name="category_id"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
<div class="filter-field"><label for="filter-product">Produk</label><select id="filter-product" name="product_id"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select></div>
<button class="button button-primary" type="submit">Terapkan filter</button>
