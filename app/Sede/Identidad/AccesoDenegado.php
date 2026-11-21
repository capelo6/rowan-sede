<?php

namespace App\Sede\Identidad;

use RuntimeException;

/** Motivo, en lenguaje claro, por el que no se admite un certificado o una firma. */
class AccesoDenegado extends RuntimeException {}
