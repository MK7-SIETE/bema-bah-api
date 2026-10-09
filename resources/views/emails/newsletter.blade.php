<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $newsletter->subject }}</title>
  <style>
    body { margin:0; padding:0; background:#f5f5f5; font-family: Arial, sans-serif; color:#222; }
    .wrapper { max-width:600px; margin:32px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    .header { background:#b8860b; padding:32px 40px; text-align:center; }
    .header h1 { margin:0; color:#fff; font-size:26px; letter-spacing:1px; }
    .header p  { margin:6px 0 0; color:rgba(255,255,255,.8); font-size:13px; }
    .body { padding:36px 40px; }
    .subject { font-size:20px; font-weight:bold; margin-bottom:20px; color:#b8860b; }
    .message { font-size:15px; line-height:1.7; white-space:pre-line; }
    .car-card { border:1px solid #e5e5e5; border-radius:8px; overflow:hidden; margin:24px 0; }
    .car-card img { width:100%; height:200px; object-fit:cover; display:block; }
    .car-card .info { padding:16px 20px; }
    .car-card .info h3 { margin:0 0 6px; font-size:17px; color:#222; }
    .car-card .info .price { font-size:18px; font-weight:bold; color:#b8860b; }
    .car-card .info .meta { font-size:13px; color:#777; margin-top:4px; }
    .promo-badge { display:inline-block; background:#b8860b; color:#fff; padding:4px 14px; border-radius:20px; font-size:13px; font-weight:bold; margin-bottom:14px; }
    .cta-btn { display:block; width:fit-content; margin:28px auto; background:#b8860b; color:#fff; text-decoration:none; padding:14px 36px; border-radius:6px; font-size:15px; font-weight:bold; }
    .footer { background:#f5f5f5; padding:20px 40px; text-align:center; font-size:12px; color:#999; border-top:1px solid #e5e5e5; }
    .footer a { color:#b8860b; }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <h1>{{ \App\Models\SiteConfig::get('dealership_name', 'Mukuba Motors') }}</h1>
      <p>{{ \App\Models\SiteConfig::get('email', '') }}</p>
    </div>

    <div class="body">

      {{-- Promotion badge --}}
      @if($newsletter->type === 'promotion')
        <div><span class="promo-badge">🎉 Special Promotion</span></div>
      @elseif($newsletter->type === 'new_car')
        <div><span class="promo-badge">🚗 New Arrival</span></div>
      @endif

      <div class="subject">{{ $newsletter->subject }}</div>

      {{-- Featured car card --}}
      @if($newsletter->car)
        @php $car = $newsletter->car; @endphp
        <div class="car-card">
          @if($car->images && count($car->images))
            <img src="{{ $car->images[0] }}" alt="{{ $car->year }} {{ $car->make }} {{ $car->model }}" />
          @endif
          <div class="info">
            <h3>{{ $car->year }} {{ $car->make }} {{ $car->model }}</h3>
            <div class="price">
              @if($car->price)
                ZMW {{ number_format($car->price) }}
              @else
                Price on request
              @endif
            </div>
            <div class="meta">
              {{ $car->mileage ? number_format($car->mileage) . ' km' : '' }}
              {{ $car->transmission ?? '' }}
              {{ $car->fuel_type ?? '' }}
            </div>
          </div>
        </div>
      @endif

      {{-- Main message body --}}
      @if($newsletter->body)
        <div class="message">{{ $newsletter->body }}</div>
      @endif

      <a href="{{ config('app.url') }}/inventory" class="cta-btn">View Our Full Inventory</a>

    </div>

    <div class="footer">
      <p>
        You are receiving this because you subscribed on our website.<br>
        <a href="{{ config('app.url') }}/unsubscribe/{{ $subscriber->token }}">Unsubscribe</a>
        &nbsp;|&nbsp;
        {{ \App\Models\SiteConfig::get('address', '') }}
      </p>
    </div>

  </div>
</body>
</html>
