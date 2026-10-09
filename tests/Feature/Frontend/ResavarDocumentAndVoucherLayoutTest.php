<?php

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class ResavarDocumentAndVoucherLayoutTest extends TestCase
{
    public function test_pdf_documents_retain_light_surfaces_and_resavar_ink_contrast(): void
    {
        $document = file_get_contents(resource_path('views/documents/booking-pdf.blade.php'));
        $agreement = file_get_contents(resource_path('views/user/owner/agreement-pdf.blade.php'));
        $report = file_get_contents(resource_path('views/admin/reports/print.blade.php'));

        $this->assertIsString($document);
        $this->assertStringContainsString('.verification-panel', $document);
        $this->assertStringContainsString('background: #F4F7FC;', $document);
        $this->assertStringContainsString('Dates of stay', $document);
        $this->assertStringContainsString('page-break-inside: avoid;', $document);
        $this->assertStringContainsString('width: 110px;', $document);
        $this->assertStringContainsString('background: #FFFFFF;', $agreement);
        $this->assertStringContainsString('word-wrap: break-word;', $agreement);
        $this->assertStringContainsString('display: table-header-group;', $report);
        $this->assertStringContainsString('color: #FFFFFF;', $report);
    }

    public function test_browser_receipts_have_distinct_printable_light_and_dark_surfaces(): void
    {
        $invoice = file_get_contents(resource_path('views/bookings/receipt.blade.php'));
        $receipt = file_get_contents(resource_path('views/public/payments/receipt.blade.php'));

        $this->assertStringContainsString('--wash:#F2F5FA', $invoice);
        $this->assertStringContainsString('background:#EEF3FA', $invoice);
        $this->assertStringContainsString('background:#EEF3FA', $receipt);
        $this->assertStringContainsString('background:#052058;color:#FFFFFF', $receipt);
        $this->assertStringContainsString("images/logo-light.png", $invoice);
        $this->assertStringContainsString("images/logo-light.png", $receipt);
    }

    public function test_admin_voucher_code_is_readonly_and_cryptographically_generated(): void
    {
        $form = file_get_contents(resource_path('views/admin/vouchers/index.blade.php'));
        $script = file_get_contents(resource_path('js/resavar-voucher-code.js'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/VoucherController.php'));

        $this->assertStringContainsString('data-voucher-code-preview', $form);
        $this->assertStringContainsString('readonly required', $form);
        $this->assertStringContainsString('type="button" data-voucher-code-regenerate', $form);
        $this->assertStringContainsString('crypto.getRandomValues', $script);
        $this->assertStringContainsString('data-restored', $form);
        $this->assertStringContainsString('random_bytes(9)', $controller);
        $this->assertStringContainsString("Rule::unique('vouchers', 'code')", $controller);
    }

    public function test_document_centre_exposes_documents_with_receipt_availability_check(): void
    {
        $index = file_get_contents(resource_path('views/user/documents/index.blade.php'));

        $this->assertStringContainsString("'confirmation'", $index);
        $this->assertStringContainsString("'invoice'", $index);
        $this->assertStringContainsString("'receipt'", $index);
        $this->assertStringContainsString('$booking->receiptAvailable()', $index);
    }

    public function test_admin_invoice_and_receipt_previews_are_distinct_and_not_nested_documents(): void
    {
        $invoice = file_get_contents(resource_path('views/admin/documents/invoice.blade.php'));
        $receipt = file_get_contents(resource_path('views/admin/documents/receipt.blade.php'));

        $this->assertStringContainsString("'documentType' => 'Invoice'", $invoice);
        $this->assertStringContainsString("'documentType' => 'Receipt'", $receipt);
        $this->assertStringNotContainsString('<!doctype html>', $invoice);
        $this->assertStringNotContainsString('<!doctype html>', $receipt);
    }
}
