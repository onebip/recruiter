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

function milliseconds($numberOf)
{
    return new Duration($numberOf);
}

function seconds($numberOf)
{
    return new Duration($numberOf * 1000);
}

function minutes($numberOf)
{
    return new Duration($numberOf * 60000);
}

function hours($numberOf)
{
    return new Duration($numberOf * 3600000);
}
