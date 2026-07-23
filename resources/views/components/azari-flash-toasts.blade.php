@if (session('success') || session('error') || session('warning') || session('info'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if (session('success')) window.AzariUI?.toast(@json(session('success')), 'success'); @endif
    @if (session('error')) window.AzariUI?.toast(@json(session('error')), 'error'); @endif
    @if (session('warning')) window.AzariUI?.toast(@json(session('warning')), 'warning'); @endif
    @if (session('info')) window.AzariUI?.toast(@json(session('info')), 'info'); @endif
});
</script>
@endif
