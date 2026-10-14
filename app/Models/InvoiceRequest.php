<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Payment\Entities\Order;

class InvoiceRequest extends Model
{
    protected $fillable = [
        'order_id', 'customer_id', 'partner_id', 'company_name', 'tax_code', 'address', 'email', 'note',
        'status', 'invoice_number', 'invoice_file', 'issued_at', 'reject_reason', 'handled_by',
    ];

    protected $casts = ['issued_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getInvoiceFileUrlAttribute(): ?string
    {
        return $this->invoice_file ? Storage::disk('public')->url($this->invoice_file) : null;
    }
}
