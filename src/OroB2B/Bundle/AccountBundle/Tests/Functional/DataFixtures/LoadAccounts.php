<?php

namespace OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use OroB2B\Bundle\AccountBundle\Entity\Account;
use OroB2B\Bundle\AccountBundle\Entity\AccountGroup;

class LoadAccounts extends AbstractFixture implements DependentFixtureInterface
{
    const DEFAULT_ACCOUNT_NAME = 'account.orphan';

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [__NAMESPACE__ . '\LoadGroups'];
    }

    /**
     * {@inheritdoc}
     *
     * account.orphan
     * account.level_1
     *     account.level_1.1
     *         account.level_1.1.1
     *     account.level_1.2
     *         account.level_1.2.1
     *             account.level_1.2.1.1
     *     account.level_1.3
     *         account.level_1.3.1
     *             account.level_1.3.1.1
     *     account.level_1.4
     *         account.level_1.4.1
     *             account.level_1.4.1.1
     * account.level_1_1
     */
    public function load(ObjectManager $manager)
    {
        $this->createAccount($manager, self::DEFAULT_ACCOUNT_NAME);

        $group1 = $this->getAccountGroup('account_group.group1');
        $group2 = $this->getAccountGroup('account_group.group2');
        $group3 = $this->getAccountGroup('account_group.group3');

        $levelOne = $this->createAccount($manager, 'account.level_1', null, $group1);

        $levelTwoFirst = $this->createAccount($manager, 'account.level_1.1', $levelOne);
        $this->createAccount($manager, 'account.level_1.1.1', $levelTwoFirst);

        $levelTwoSecond = $this->createAccount($manager, 'account.level_1.2', $levelOne, $group2);
        $levelTreeFirst = $this->createAccount($manager, 'account.level_1.2.1', $levelTwoSecond, $group2);
        $this->createAccount($manager, 'account.level_1.2.1.1', $levelTreeFirst, $group2);

        $levelTwoThird = $this->createAccount($manager, 'account.level_1.3', $levelOne, $group1);
        $levelTreeFirst = $this->createAccount($manager, 'account.level_1.3.1', $levelTwoThird, $group3);
        $this->createAccount($manager, 'account.level_1.3.1.1', $levelTreeFirst, $group3);

        $levelTwoFourth = $this->createAccount($manager, 'account.level_1.4', $levelOne, $group3);
        $levelTreeFourth = $this->createAccount($manager, 'account.level_1.4.1', $levelTwoFourth);
        $this->createAccount($manager, 'account.level_1.4.1.1', $levelTreeFourth);

        $this->createAccount($manager, 'account.level_1_1');

        $manager->flush();
    }

    /**
     * @param string $reference
     * @return AccountGroup
     */
    protected function getAccountGroup($reference)
    {
        return $this->getReference($reference);
    }

    /**
     * @param ObjectManager $manager
     * @param string $name
     * @param Account $parent
     * @param AccountGroup $group
     * @return Account
     */
    protected function createAccount(
        ObjectManager $manager,
        $name,
        Account $parent = null,
        AccountGroup $group = null
    ) {
        $account = new Account();
        $account->setName($name);
        $organization = $manager
            ->getRepository('OroOrganizationBundle:Organization')
            ->getFirst();
        $account->setOrganization($organization);
        if ($parent) {
            $account->setParent($parent);
        }
        if ($group) {
            $account->setGroup($group);
        }
        $manager->persist($account);
        $this->addReference($name, $account);

        return $account;
    }
}
