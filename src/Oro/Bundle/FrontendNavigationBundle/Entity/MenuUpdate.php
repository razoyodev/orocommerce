<?php

namespace Oro\Bundle\FrontendNavigationBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\FrontendNavigationBundle\Model\ExtendMenuUpdate;
use Oro\Bundle\NavigationBundle\Entity\MenuUpdateInterface;
use Oro\Bundle\NavigationBundle\Entity\MenuUpdateTrait;

/**
 * @ORM\Entity
 * @ORM\Table(name="oro_front_nav_menu_upd")
 * @ORM\AssociationOverrides({
 *      @ORM\AssociationOverride(
 *          name="titles",
 *          joinTable=@ORM\JoinTable(
 *              name="oro_front_nav_menu_upd_title",
 *              joinColumns={
 *                  @ORM\JoinColumn(
 *                      name="menu_update_id",
 *                      referencedColumnName="id",
 *                      onDelete="CASCADE"
 *                  )
 *              },
 *              inverseJoinColumns={
 *                  @ORM\JoinColumn(
 *                      name="localized_value_id",
 *                      referencedColumnName="id",
 *                      onDelete="CASCADE",
 *                      unique=true
 *                  )
 *              }
 *          )
 *      )
 * })
 * @Config(
 *      defaultValues={
 *          "entity"={
 *              "icon"="icon-th"
 *          }
 *      }
 * )
 */
class MenuUpdate extends ExtendMenuUpdate implements
    MenuUpdateInterface
{
    use MenuUpdateTrait;

    /**
     * @var string
     *
     * @ORM\Column(name="`condition`", type="string", length=512, nullable=true)
     */
    protected $condition;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();

        $this->titles = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getExtras()
    {
        $extras = [
            'image' => $this->getImage(),
            'condition' => $this->getCondition(),
            'divider' => $this->isDivider()
        ];

        if ($this->getPriority() !== null) {
            $extras['position'] = $this->getPriority();
        }

        return $extras;
    }

    /**
     * @return string
     */
    public function getCondition()
    {
        return $this->condition;
    }

    /**
     * @param string $condition
     * @return MenuUpdate
     */
    public function setCondition($condition)
    {
        $this->condition = $condition;

        return $this;
    }
}
