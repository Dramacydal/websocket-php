<?php

/**
 * Copyright (C) 2014-2024 Textalk and contributors.
 * This file is part of Websocket PHP and is free software under the ISC License.
 */

namespace WebSocket\TraitNs;

/**
 * WebSocket\Trait\StringableTrait trait.
 * Stringable helper.
 */
trait StringableTrait
{
    public function __toString(): string
    {
        return get_class($this);
    }

    protected function stringable(string $format, ...$values): string
    {
        return sprintf("%s({$format})", get_class($this), ...$values);
    }
}
