<?php

namespace App\Support;

final class ArabicLabels
{
    public static function status(?string $value): string
    {
        return match ($value) {
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'blocked' => 'محظور',
            'draft' => 'مسودة',
            'approved' => 'معتمد',
            'confirmed' => 'مؤكد',
            'cancelled' => 'ملغي',
            'partially_received' => 'مستلم جزئيًا',
            'fully_received' => 'مستلم بالكامل',
            'paid' => 'مسدد',
            'partially_paid' => 'مسدد جزئيًا',
            'unpaid' => 'غير مسدد',
            'pending' => 'قيد الانتظار',
            null, '' => '—',
            default => $value,
        };
    }

    public static function paymentType(?string $value): string
    {
        return match ($value) {
            'invoice' => 'سداد فاتورة',
            'advance' => 'دفعة مقدمة',
            'payment' => 'دفعة',
            null, '' => '—',
            default => $value,
        };
    }

    public static function paymentMethod(?string $value): string
    {
        return match ($value) {
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
            null, '' => '—',
            default => $value,
        };
    }
}
