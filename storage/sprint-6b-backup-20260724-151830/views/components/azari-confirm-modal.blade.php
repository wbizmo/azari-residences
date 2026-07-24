@props(['id'])
<div class="az-modal" id="{{ $id }}" data-az-modal hidden aria-hidden="true">
    <div class="az-modal__backdrop" data-az-modal-close></div>
    <section class="az-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" tabindex="-1">
        <header class="az-modal__header">
            <div class="az-modal__icon"><span class="material-symbols-outlined">warning</span></div>
            <div><span class="az-modal__eyebrow">Please review</span><h2 id="{{ $id }}-title" data-az-modal-title>Confirm action</h2></div>
            <button type="button" class="az-modal__close" data-az-modal-close aria-label="Close modal"><span class="material-symbols-outlined">close</span></button>
        </header>
        <div class="az-modal__body"><p data-az-modal-message>Confirm this action before continuing.</p></div>
        <form method="POST" class="az-modal__form" data-az-modal-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-az-modal-method>
            <input type="hidden" name="reason" value="" data-az-modal-reason>
            <input type="hidden" name="status" value="" data-az-modal-status>
            <footer class="az-modal__footer">
                <button type="button" class="az-button az-button--secondary" data-az-modal-close>Keep current state</button>
                <button type="submit" class="az-button az-button--danger" data-az-modal-confirm>Continue</button>
            </footer>
        </form>
    </section>
</div>
