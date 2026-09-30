<?php

namespace App\Metier\Licence;

use RuntimeException;

/** Une activation refusée, avec la phrase à rendre telle quelle à l'installation. */
class ActivationRefusee extends RuntimeException {}
