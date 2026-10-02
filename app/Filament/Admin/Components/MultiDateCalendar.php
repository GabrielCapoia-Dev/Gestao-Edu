<?php

namespace App\Filament\Admin\Components;

use Closure;
use Filament\Forms\Components\Field;

class MultiDateCalendar extends Field
{
    protected string $view = 'filament.admin.components.multi-date-calendar';

    protected int|Closure $maxSelectableDays = 0;

    protected array $holidayRules = [];

    public function maxSelectableDays(int|Closure $count): static
    {
        $this->maxSelectableDays = $count instanceof Closure ? $count : max(0, $count);

        return $this;
    }

    /** @param array{fixed: array<string, string>, easterOffsets: array<int, string>} $rules */
    public function holidayRules(array $rules): static
    {
        $this->holidayRules = $rules;

        return $this;
    }

    public function getMaxSelectableDays(): int
    {
        return max(0, (int) $this->evaluate($this->maxSelectableDays));
    }

    /** @return array{fixed: array<string, string>, easterOffsets: array<int, string>} */
    public function getHolidayRules(): array
    {
        return $this->holidayRules;
    }
}
