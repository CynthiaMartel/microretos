<?php

namespace App\Exceptions;

use RuntimeException;

/** La API de la web no responde, no está configurada o rechaza la firma (Primer contacto). */
class FrontofficeNoDisponible extends RuntimeException {}
