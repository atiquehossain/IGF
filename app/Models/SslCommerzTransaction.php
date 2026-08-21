<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SslCommerzTransaction extends Model
{

  use HasFactory;

  protected $fillable = [
    'tran_id',
    'val_id',
    'bank_tran_id',
    'subscription_id',
    'status',
    'requested_payment_method',
    'amount',
    'store_amount',
    'currency',
    'currency_type',
    'currency_amount',
    'currency_rate',
    'base_fair',
    'card_type',
    'card_issuer',
    'card_brand',
    'card_issuer_country',
    'card_issuer_country_code',
    'risk_level',
    'risk_title',
    'cus_name',
    'cus_email',
    'cus_phone',
    'opted_a',
    'opted_b',
    'opted_c',
    'opted_d',
    'raw_response',
  ];

  protected $casts = [
    'amount'          => 'float',
    'store_amount'    => 'float',
    'currency_amount' => 'float',
    'currency_rate'   => 'float',
    'base_fair'       => 'float',
    'gateway_redirect_url' => 'encrypted',
    'initialization_completed_at' => 'datetime',
  ];

  // -------------------------------------------------------------------------
  // Scopes
  // -------------------------------------------------------------------------

  public function scopeSuccessful($query)
  {
    return $query->whereIn('status', ['VALID', 'VALIDATED']);
  }

  public function scopePending($query)
  {
    return $query->where('status', 'PENDING');
  }

  public function scopeFailed($query)
  {
    return $query->whereIn('status', ['FAILED', 'CANCELLED']);
  }

  // -------------------------------------------------------------------------
  // Accessors
  // -------------------------------------------------------------------------

  public function getIsSuccessfulAttribute(): bool
  {
    return in_array(strtoupper($this->status), ['VALID', 'VALIDATED'], true);
  }

  public function getDecodedResponseAttribute(): array
  {
    return json_decode($this->raw_response, true) ?? [];
  }

  public function getVerifiedPaymentMethodLabelAttribute(): string
  {
    $details = strtolower(implode(' ', array_filter([
      $this->card_type,
      $this->card_brand,
      $this->card_issuer,
    ])));

    return match (true) {
      str_contains($details, 'bkash') => 'bKash',
      str_contains($details, 'nagad') => 'Nagad',
      str_contains($details, 'amex'), str_contains($details, 'american express') => 'American Express',
      str_contains($details, 'visa') => 'Visa',
      default => $this->card_type ?: $this->card_brand ?: 'Online payment',
    };
  }
}
