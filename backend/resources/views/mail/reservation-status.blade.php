<h1>{{ $event === \App\Support\ReservationNotificationService::EVENT_PAYMENT_CONFIRMED ? 'Payment confirmed' : 'Reservation received' }}</h1>

<p>
    @if ($forBranch)
        A customer reservation needs your attention at {{ $reservation->branch->name }}.
    @elseif ($event === \App\Support\ReservationNotificationService::EVENT_PAYMENT_CONFIRMED)
        We have confirmed payment for your reservation. The branch will contact you when your order is ready for collection.
    @else
        We have received your reservation. Your stock hold is temporary until payment is confirmed.
    @endif
</p>

<dl>
    <dt>Reservation</dt>
    <dd>{{ $reservation->reservation_number }}</dd>
    <dt>Product</dt>
    <dd>{{ $reservation->product_name }}</dd>
    <dt>Quantity</dt>
    <dd>{{ $reservation->quantity }}</dd>
    <dt>Status</dt>
    <dd>{{ str_replace('_', ' ', $reservation->status) }}</dd>
</dl>

@if ($forBranch)
    <p>Customer: {{ $reservation->customer_name }}@if ($reservation->customer_phone), {{ $reservation->customer_phone }}@endif</p>
@endif
