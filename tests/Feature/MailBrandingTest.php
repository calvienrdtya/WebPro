<?php

namespace Tests\Feature;

use App\Mail\PaymentReceivedMail;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_footer_sign_off_and_sender_use_the_store_name_not_app_name(): void
    {
        config(['app.name' => 'NamaAplikasi', 'mail.default' => 'array', 'mail.from' => ['address' => 'toko@uji.test', 'name' => 'NamaAplikasi']]);
        Setting::put('store.name', 'Toko Uji Jaya');
        $order = Order::factory()->create(['customer_email' => 'budi@uji.test']);

        $html = (new PaymentReceivedMail($order))->render();
        $this->assertSame(4, substr_count($html, 'Toko Uji Jaya')); // judul, kepala, penutup, kaki
        $this->assertStringNotContainsString('NamaAplikasi', $html);

        Mail::to('budi@uji.test')->send(new PaymentReceivedMail($order));
        $sent = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame('Toko Uji Jaya', $sent->getFrom()[0]->getName());
        $this->assertSame('toko@uji.test', $sent->getFrom()[0]->getAddress());
    }
}
