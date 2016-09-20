<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/7/11
 * Time: 下午3:39
 */

namespace x2ts;

/**
 * Class Utils
 *
 * @package x2ts
 */
class Utils extends Component {
    protected static $_conf = [
        'ldap' => [
            'host'           => 'localhost',
            'port'           => 389,
            'dn_base'        => 'ou=staffs,dc=example,dc=com',
            'auth_key'       => 'uid',
            'admin_dn'       => 'cn=admin,dc=example,dc=com',
            'admin_password' => '',
        ],
    ];

    public function ldap_auth(string $username, string $password):bool {
        $c = ldap_connect($this->conf['ldap']['host'], $this->conf['ldap']['port']);
        if (!$c) {
            throw new LDAPException('Cannot connect to LDAP server');
        }
        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        $dn = "{$this->conf['ldap']['auth_key']}={$username},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace('LDAP DN:' . $dn);
        $r = @ldap_bind($c, $dn, $password);
        ldap_close($c);
        return (bool) $r;
    }

    public function ldap_user(string $username) {
        $c = ldap_connect($this->conf['ldap']['host'], $this->conf['ldap']['port']);
        if (!$c) {
            throw new LDAPException('Cannot connect to LDAP server');
        }
        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        $dn = "{$this->conf['ldap']['auth_key']}={$username},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace("LDAP DN: $dn");
        $result = @ldap_read($c, $dn, 'objectClass=person');
        if ($result === false) {
            ldap_close($c);
            return null;
        }
        $entries = ldap_get_entries($c, $result);
        ldap_free_result($result);
        ldap_close($c);
        if ($entries['count']) {
            return $entries[0];
        }
        return null;
    }

    public function ldap_change_password(string $username, string $old_password, string $new_password) {
        $c = ldap_connect($this->conf['ldap']['host'], $this->conf['ldap']['port']);
        if (!$c) {
            throw new LDAPException('Cannot connect to LDAP server');
        }
        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        $dn = "{$this->conf['ldap']['auth_key']}={$username},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace("LDAP DN: $dn");
        $r = @ldap_bind($c, $dn, $old_password);
        if (!$r) {
            return false;
        }

        $r = ldap_modify($c, $dn, [
            'userPassword' => $this->hash_ssha($new_password),
        ]);
        ldap_close($c);
        return $r;
    }

    public function ldap_set_password(string $username, string $new_password) {
        $c = ldap_connect($this->conf['ldap']['host'], $this->conf['ldap']['port']);
        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        $dn = "{$this->conf['ldap']['auth_key']}={$username},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace("LDAP DN: $dn");
        if (!ldap_bind($c, $this->conf['ldap']['admin_dn'], $this->conf['ldap']['admin_password'])) {
            return false;
        }
        $r = ldap_modify($c, $dn, [
            'userPassword' => $this->hash_ssha($new_password),
        ]);
        ldap_close($c);
        return $r;
    }

    public function is_lan_ip(string $ipv4, bool $loopBack = false, bool $linkLocal = false):bool {
        $long = ip2long($ipv4);
        return
            $long & 0xff000000 === 0xa0000000 || // 10.0.0.0/8
            $long & 0xfff00000 === 0xac100000 || // 172.16.0.0/12
            $long & 0xffff0000 === 0xc0a80000 || // 192.168.0.0/16
            $loopBack &&
            $long & 0xff000000 === 0x7f000000 || // 127.0.0.0/8
            $linkLocal &&
            $long & 0xffff0000 === 0xa9fe0000;   // 169.254.0.0/16
    }

    public function random_chars(int $length):string {
        return substr(
            str_replace(['+', '/', '='], '', base64_encode(
                random_bytes($length << 1)
            )),
            0,
            $length
        );
    }

    private function hash_ssha(string $password):string {
        $salt = random_bytes(6);
        return '{SSHA}' . base64_encode(sha1($password . $salt, true) . $salt);
    }
}