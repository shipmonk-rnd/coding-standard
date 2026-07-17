<?php declare(strict_types = 1);

namespace App;

final class TypeCommentRecovery
{

    public function __construct(
        public Kind $kind,
        public AlphaPayload | // legacy
        BetaPayload | // deprecated
        GammaPayload $payload,
    )
    {
    }

}
