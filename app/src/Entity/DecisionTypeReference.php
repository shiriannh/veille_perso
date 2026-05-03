<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class DecisionTypeReference implements ReferenceEntityInterface
{
    use ReferenceEntityTrait;
}
