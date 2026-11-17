<?php

namespace App\Enums;

/**
 * Colors a project can be tagged with.
 *
 * The Tailwind classes are written out in full so Tailwind can find them
 * (this folder is listed as a @source in resources/css/app.css).
 */
enum ProjectColor: string
{
    case Slate = 'slate';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Emerald = 'emerald';
    case Teal = 'teal';
    case Sky = 'sky';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Pink = 'pink';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Slate => 'bg-slate-500',
            self::Red => 'bg-red-500',
            self::Orange => 'bg-orange-500',
            self::Amber => 'bg-amber-400',
            self::Emerald => 'bg-emerald-500',
            self::Teal => 'bg-teal-500',
            self::Sky => 'bg-sky-500',
            self::Indigo => 'bg-indigo-500',
            self::Violet => 'bg-violet-500',
            self::Pink => 'bg-pink-500',
        };
    }
}
