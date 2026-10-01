<?php

declare(strict_types=1);

namespace Eram\Daynum;

/**
 * The four seasons. In the Jalali calendar each season is exactly one
 * quarter: Spring is Farvardin–Khordad, Summer Tir–Shahrivar, Autumn
 * Mehr–Azar, Winter Dey–Esfand. See `JalaliView::season()`.
 */
enum Season: int
{
    case Spring = 1;
    case Summer = 2;
    case Autumn = 3;
    case Winter = 4;
}
