<x-admin.layout title="Dashboard">
    <div class="admin-heading">
        <div><span>Overview</span><h1>Administration dashboard</h1></div>
    </div>
    <div class="admin-stat-grid">
        <article><strong>{{ $propertyCount }}</strong><span>Properties</span></article>
        <article><strong>{{ $featuredCount }}</strong><span>Featured</span></article>
        <article><strong>{{ $contentCount }}</strong><span>Content blocks</span></article>
        <article><strong>{{ $staffCount }}</strong><span>Staff accounts</span></article>
    </div>
</x-admin.layout>
