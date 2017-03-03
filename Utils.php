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
            'search_timeout' => 5,
            'net_timeout'    => 5,
        ],
    ];

    public function ldap_auth(string $username, string $password): bool {
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
        $c = $this->get_ldap_connection();
        $dn = "{$this->conf['ldap']['auth_key']}={$username},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace("LDAP DN: $dn");
        $result = @ldap_read($c, $dn, 'objectClass=person');
        if ($result === false) {
            ldap_close($c);
            return null;
        }
        $entries = ldap_get_entries($c, $result);
        Toolkit::trace($entries);
        ldap_free_result($result);
        ldap_close($c);
        if ($entries['count']) {
            return $entries[0];
        }
        return null;
    }

    public function ldap_mail(string $mail) {
        $c = $this->get_ldap_connection();
        $dn = "{$this->conf['ldap']['dn_base']}";
        $filter="(mail=$mail)";
        $justthese = array("uid","mail");
        $result=ldap_search($c, $dn, $filter, $justthese);
        $entries = ldap_get_entries($c, $result);
        Toolkit::trace($entries);
        ldap_free_result($result);
        ldap_close($c);
        if($entries["count"] > 0){
            return true;
        }
        return false;
    }

    public function ldap_change_password(string $username, string $old_password, string $new_password) {
        $c = $this->get_ldap_connection();
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
        $c = $this->get_ldap_connection();
        $dn = "{$this->conf['ldap']['auth_key']}={$username},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace("LDAP DN: $dn");
        if (!ldap_bind($c, $this->conf['ldap']['admin_dn'], $this->conf['ldap']['admin_password'])) {
            return false;
        }
        $entry = ['userPassword' => $this->hash_ssha($new_password)];
        $r = @ldap_modify($c, $dn, $entry);
        if ($r === false && 32 === ldap_errno($c)) {
            $r = ldap_add($c, $dn, $entry);
        }
        ldap_close($c);
        return $r;
    }

    public function ldap_add_user(array $user) {
        $c = $this->get_ldap_connection();
        $dn = "{$this->conf['ldap']['auth_key']}={$user[$this->conf['ldap']['auth_key']]},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace("LDAP DN: $dn");
        if (!ldap_bind($c, $this->conf['ldap']['admin_dn'], $this->conf['ldap']['admin_password'])) {
            return false;
        }
        Toolkit::trace("adding user");
        Toolkit::trace($user);
        if (ldap_add($c, $dn, $user) === false) {
            throw new LDAPException(ldap_error($c), ldap_errno($c));
        }
        ldap_close($c);
        return true;
    }

    public function is_lan_ip(string $ipv4, bool $loopBack = false, bool $linkLocal = false): bool {
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

    public function random_chars(int $length): string {
        return Toolkit::random_chars($length);
    }

    /**
     * @param string       $url
     * @param string|array $body
     * @param array        $headers
     *
     * @return array|bool
     */
    public function curlPost(string $url, $body, array $headers = []) {
        $c = $this->curlInit($url, $headers);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $body);

        $r = curl_exec($c);
        curl_close($c);
        if (false === $r) {
            return false;
        }

        return $this->parseHttpResponse($r);
    }

    public function curlGet(string $url, array $headers = []) {
        $c = $this->curlInit($url, $headers);

        $r = curl_exec($c);
        curl_close($c);
        if (false === $r) {
            return false;
        }

        return $this->parseHttpResponse($r);
    }

    private function get_ldap_connection() {
        $c = ldap_connect($this->conf['ldap']['host'], $this->conf['ldap']['port']);
        if (!$c) {
            throw new LDAPException('Cannot connect to LDAP server');
        }
        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($c, LDAP_OPT_TIMELIMIT, $this->conf['ldap']['search_timeout']);
        ldap_set_option($c, LDAP_OPT_NETWORK_TIMEOUT, $this->conf['ldap']['net_timeout']);
        return $c;
    }

    private function hash_ssha(string $password): string {
        $salt = random_bytes(6);
        return '{SSHA}' . base64_encode(sha1($password . $salt, true) . $salt);
    }

    /**
     * @param  string $r
     *
     * @return array
     */
    private function parseHttpResponse(string $r): array {
        list($header, $body) = explode("\r\n\r\n", $r, 2);
        $headerList = explode("\r\n", $header);
        $statusLine = array_shift($headerList);
        $statusCode = (int) explode(' ', $statusLine)[1];
        $headers = [];
        foreach ($headerList as $header) {
            list($key, $value) = explode(':', $header, 2);
            $headers[$key] = $value;
        }
        return [
            'status'  => $statusCode,
            'headers' => $headers,
            'body'    => $body,
        ];
    }

    /**
     * @param string $url
     * @param array  $headers
     *
     * @return resource
     */
    private function curlInit(string $url, array $headers) {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_HEADER, true);
        if (count($headers) > 0) {
            $headerList = [];
            foreach ($headers as $name => $value) {
                $headerList[] = "$name: $value";
            }
            curl_setopt($c, CURLOPT_HTTPHEADER, $headerList);
            return $c;
        }
        return $c;
    }
}