<?php

namespace App\DTOs;

/**
 * Umbrales de confianza (§8 AGENTS.md).
 */
final class BookStructureThresholds
{
    /** >= 85% aceptar automáticamente */
    public const AUTO_ACCEPT = 85;
    /** 60–84% mostrar advertencia / pedir confirmación */
    public const WARN_MIN = 60;
}
