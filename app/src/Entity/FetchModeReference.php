<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class FetchModeReference implements ReferenceEntityInterface
{
    use ReferenceEntityTrait;
}
