<div class="calendar-block">
    <div class="calendar-block-title">{{ $calendario['label'] }}</div>

    <table class="calendar-month">
        <thead>
            <tr>
                @foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $weekday)
                    <th>{{ $weekday }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($calendario['weeks'] as $week)
                <tr>
                    @foreach ($week as $day)
                        @if ($day === null)
                            <td class="calendar-empty"></td>
                        @else
                            <td>
                                <span class="calendar-day-number">{{ $day['date']->format('d') }}</span>

                                @if ($day['eventsCount'] > 0)
                                    <span class="calendar-event-count">
                                        {{ $day['eventsCount'] }} evento(s)
                                    </span>
                                    <div class="calendar-markers">
                                        @foreach ($day['colors'] as $color)
                                            <span class="calendar-marker" style="background: {{ $color }};"></span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="calendar-summary">
        @foreach ($calendario['resumo'] as $item)
            <p class="calendar-summary-item">
                <span class="calendar-summary-dot" style="background: {{ $item['color'] }};"></span>
                <strong>{{ $item['count'] }}</strong>
                {{ $item['count'] === 1 ? $item['singular'] : $item['plural'] }}
            </p>
        @endforeach
    </div>
</div>
