<?php

namespace Timeless;

function clock($clock = null)
{
    global $__2852bec4cda046fca0e5e21dc007935c;
    $__2852bec4cda046fca0e5e21dc007935c =
        $clock ?: (
            $__2852bec4cda046fca0e5e21dc007935c ?: new Clock()
        );
    return $__2852bec4cda046fca0e5e21dc007935c;
}

function now()
{
    return clock()->now();
}

function millisecond($numberOf)
{
    return milliseconds($numberOf);
}

function milliseconds($numberOf)
{
    return new Duration($numberOf);
}

function second($numberOf)
{
    return seconds($numberOf);
}

function seconds($numberOf)
{
    return new Duration($numberOf * 1000);
}

function minute($numberOf)
{
    return minutes($numberOf);
}

function minutes($numberOf)
{
    return new Duration($numberOf * 60000);
}

function hour($numberOf)
{
    return hours($numberOf);
}

function hours($numberOf)
{
    return new Duration($numberOf * 3600000);
}

function day($numberOf)
{
    return days($numberOf);
}

function days($numberOf)
{
    return new Duration($numberOf * 86400000);
}

function week($numberOf)
{
    return weeks($numberOf);
}

function weeks($numberOf)
{
    return new Duration($numberOf * 604800000);
}

function month($numberOf)
{
    return months($numberOf);
}

function months($numberOf)
{
    return new Duration($numberOf * 2592000000);
}

function year($numberOf)
{
    return years($numberOf);
}

function years($numberOf)
{
    return new Duration($numberOf * 6622560000000);
}

