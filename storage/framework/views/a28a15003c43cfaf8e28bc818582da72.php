<div
    class="modal-layer"
    id="verification-modal"
    data-modal
    hidden
    aria-hidden="true"
>
    <div class="modal-backdrop" data-modal-close></div>

    <section
        class="modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="verification-modal-title"
    >
        <button class="modal-close" type="button" data-modal-close aria-label="Close dialog">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>

        <span class="eyebrow">Verification</span>
        <h2 id="verification-modal-title">Verify a booking</h2>
        <p>
            Public booking, invoice and receipt verification will be connected
            in Sprint 9. This interface is ready for that workflow.
        </p>

        <form class="verification-form" data-demo-form data-success-message="Verification services will become active in Sprint 9.">
            <label for="verification-code">Booking or document reference</label>
            <div class="field-with-action">
                <input
                    class="luxury-input"
                    id="verification-code"
                    name="reference"
                    type="text"
                    placeholder="AZR-000000"
                    autocomplete="off"
                    required
                >
                <button class="button button-primary" type="submit">Verify</button>
            </div>
        </form>
    </section>
</div>
<?php /**PATH /home/runner/workspace/resources/views/public/partials/modal-root.blade.php ENDPATH**/ ?>