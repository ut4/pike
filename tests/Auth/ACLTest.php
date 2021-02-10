<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use PHPUnit\Framework\TestCase;
use Pike\Auth\ACL;

final class ACLTest extends TestCase {
    public function testCanDisallowsEverythingByDefault() {
        $resources = (object)[
            'res1' => (object)['action1' => 1 << 1]
        ];
        $userPermissions = (object)[
            ACL::ROLE_EDITOR => (object)['res1' => 0],
            ACL::ROLE_AUTHOR => null
        ];
        $acl = $this->createAcl($resources, $userPermissions);
        $this->assertEquals(false, $acl->can(ACL::ROLE_EDITOR, 'action1', 'res1'));
        $this->assertEquals(false, $acl->can(ACL::ROLE_AUTHOR, 'action1', 'res1'));
        $noSuchRole = ACL::ROLE_LAST + 1;
        $this->assertEquals(false, $acl->can($noSuchRole, 'action1', 'res1'));
    }


    ////////////////////////////////////////////////////////////////////////////

    
    public function testAclAllowsConfiguredActions() {
        $acl = $this->makeAclWithBasicConfig();
        $this->assertFalse($acl->can(ACL::ROLE_EDITOR,      'create',   'products'));
        $this->assertTrue($acl->can(ACL::ROLE_EDITOR,       'edit',     'products'));
        $this->assertTrue($acl->can(ACL::ROLE_EDITOR,       'comment',  'products'));
        $this->assertTrue($acl->can(ACL::ROLE_EDITOR,       'post',     'reviews'));
        $this->assertTrue($acl->can(ACL::ROLE_EDITOR,       'moderate', 'reviews'));
        //
        $this->assertFalse($acl->can(ACL::ROLE_CONTRIBUTOR, 'create',   'products'));
        $this->assertFalse($acl->can(ACL::ROLE_CONTRIBUTOR, 'edit',     'products'));
        $this->assertTrue($acl->can(ACL::ROLE_CONTRIBUTOR,  'comment',  'products'));
        $this->assertTrue($acl->can(ACL::ROLE_CONTRIBUTOR,  'post',     'reviews'));
        $this->assertFalse($acl->can(ACL::ROLE_CONTRIBUTOR, 'moderate', 'reviews'));
        //
        $this->assertTrue($acl->can(ACL::ROLE_SUPER_ADMIN,  'create',   'products'));
        $this->assertTrue($acl->can(ACL::ROLE_SUPER_ADMIN,  'edit',     'products'));
        $this->assertTrue($acl->can(ACL::ROLE_SUPER_ADMIN,  'comment',  'products'));
        $this->assertTrue($acl->can(ACL::ROLE_SUPER_ADMIN,  'post',     'reviews'));
        $this->assertTrue($acl->can(ACL::ROLE_SUPER_ADMIN,  'moderate', 'reviews'));
    }
    private function makeAclWithBasicConfig() {
        $resources = (object) [
            'products' => (object) [
                'create'  => 1 << 1,
                'edit'    => 1 << 2,
                'comment' => 1 << 3,
            ],
            'reviews' => (object) [
                'post'     => 1 << 1,
                'moderate' => 1 << 2,
            ]
        ];
        $userPermissions = (object) [
            ACL::ROLE_EDITOR => (object) [
                'products' => ACL::makePermissions(['comment', 'edit'], $resources->products),
                'reviews'  => ACL::makePermissions('*', $resources->reviews),
            ],
            ACL::ROLE_CONTRIBUTOR => (object) [
                'products' => ACL::makePermissions(['comment'], $resources->products),
                'reviews'  => ACL::makePermissions(['post'], $resources->reviews),
            ]
        ];
        return $this->createAcl($resources, $userPermissions);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testCanAllowsOnlyWhenUserHasPermission() {
        $resources = (object)[
            'res1' => (object)[
                'action1' => 1 << 1,
                'action2' => 1 << 2,
                'action3' => 1 << 3
            ]
        ];
        $acl = $this->createAclAndPermit(0, $resources);
        $this->assertEquals(false, $acl->can(ACL::ROLE_EDITOR, 'action1', 'res1'));
        $this->assertEquals(false, $acl->can(ACL::ROLE_EDITOR, 'action2', 'res1'));
        $this->assertEquals(false, $acl->can(ACL::ROLE_EDITOR, 'action3', 'res1'));
        //
        $acl2 = $this->createAclAndPermit(0 | (1 << 2), $resources);
        $this->assertEquals(false, $acl2->can(ACL::ROLE_EDITOR, 'action1', 'res1'));
        $this->assertEquals(true,  $acl2->can(ACL::ROLE_EDITOR, 'action2', 'res1'));
        $this->assertEquals(false, $acl2->can(ACL::ROLE_EDITOR, 'action3', 'res1'));
        //
        $acl3 = $this->createAclAndPermit(0 | (1 << 1) | (1 << 3), $resources);
        $this->assertEquals(true,  $acl3->can(ACL::ROLE_EDITOR, 'action1', 'res1'));
        $this->assertEquals(false, $acl3->can(ACL::ROLE_EDITOR, 'action2', 'res1'));
        $this->assertEquals(true,  $acl3->can(ACL::ROLE_EDITOR, 'action3', 'res1'));
    }
    private function createAclAndPermit($permissions, $resources) {
        $acl = $this->createAcl($resources,
                                (object)[ACL::ROLE_EDITOR => (object)[
                                    'res1' => $permissions
                                ]]);
        return $acl;
    }
    private function createAcl($resources, $userPermissions) {
        $out = new ACL;
        $out->setRules((object)['resources' => $resources,
                                'userPermissions' => $userPermissions]);
        return $out;
    }
}
