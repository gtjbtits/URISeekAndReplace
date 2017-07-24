<?php

class URISeekAndReplace {
    private $protocols = "mailto|http";
    private $domains = "aero|asia|biz|cat|com|coop|edu|gov|info|int|jobs|mil|mobi|museum|name|net|org|pro|tel|travel|tech|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|ct|cu|cv|cx|cy|cz|cz|de|dj|dk|dm|do|dz|ec|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|group|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mn|mn|mo|mp|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|nom|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ra|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw|arpa|рф";
    private $uriRegexes = [];
    /**
     * Различные части регулярных выражений, используемые при компиляции полного шаблона
     * 
     * @var array
     */
    private $uriRegexParts = [];
    private $regexFlags = "u";
    
//    private $testString = "Sample text for testing:
//        abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ
//        0123456789 _+-.,!@#$%^&*();\/|<>\"'
//        12345 -98.7 3.141 .6180 9,000 +42
//        555.123.4567  +1-(800)-555-2468
//        foo@demo.net  bar.ba@test.co.uk
//        www.demo.com  http://foo.co.uk/
//        http://regexr.com/foo.html?q=bar
//        https://mediatemple.net
//        \n https://dreamteams.atlassian.net/browse/MES-1425
//        \n google..................com
//        \n google.com
//        \n Николай Зайцев, [18.07.17 17:21]
//        \n mailto:google@com.com
//        \n mailto:me@me.ct
//        \n https://80.80.80.80/fwefew543565443^%&E#&#@$%@%$%$#%#^@^@#\$EWQE
//        \n банк.рф:8080
//        \n 11.txt";
    
    private $testString = "rostelecom.omnichat.tech";
    
    public function test() {
        echo "||||||||||||||||||| URISeekAndReplace TEST |||||||||||||||||||\n\n";
        echo "--- Source ---\n{$this->testString}\n\n"; 
        $this->processText($this->testString);
        echo "--- Result ---\n{$this->testString}\n\n"; 
    }
    
    public function __construct() {
        $this->uriRegexes["abstract"] = "/(?:(\w{2,16}):(?:\/){0,2})?(?:[a-zа-я0-9]{2,}[\.:@])+\S{2,}/i";
//        $this->uriRegexParts["ipv4"] = "(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))";
        $this->uriRegexParts["ipv4"] = "(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))";
        $this->uriRegexParts["host"] = "(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:{$this->domains}))";
        $this->uriRegexParts["port"] = ":\d{2,5}";
        $this->uriRegexParts["mailName"] = "[^\s]*";
        $this->uriRegexParts["path"] = "\/[^\s]*";
    }
    
    public function processText(&$text) {
        $abstracts = [];
        preg_match_all($this->uriRegexes["abstract"], $text, $abstracts);
        foreach ($abstracts[0] as $k=>$link) {
            $protocol = $abstracts[1][$k];
//            var_dump("Handle {$link}. Protocol: {$protocol}");
            if (!empty($protocol))
                $replaced = $this->callReplacer($protocol, $link);
            else
                $replaced = $this->replaceUnknown($link);
            $text = str_replace($link, $replaced, $text);
        }
    }
    
    /**
     * Выполняет разбор http-подобных URI
     * 
     * Группы, используемые в регулярном выражении:
     * 
     * Протокол (может отсутствовать) - 1
     * Имя хоста или IP - 2
     * Порт (может отсутствовать) - 3
     * Путь до ресурса - 4
     * 
     * Используемые флаги:
     * 
     * u - UNICODE
     * 
     * @param string $src
     * @return string
     */
    public function replaceHttp($src) {
        $regex = "/^((?:https?|sftp|ftps?):\/\/)?({$this->uriRegexParts["ipv4"]}|{$this->uriRegexParts["host"]})({$this->uriRegexParts["port"]})?({$this->uriRegexParts["path"]})?$/{$this->regexFlags}";
        if (($matches = $this->pregMatch($src, $regex)) === false)
            return $src;
        $protocol = !empty($matches[1]) ? $matches[1] : "";
        $host = $matches[2];
        $port = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : "";
        $path = isset($matches[4]) && !empty($matches[4]) ? $matches[4] : "";
        $needle = "{$protocol}{$host}{$port}{$path}";
        return $this->replace($needle, (empty($protocol) ? "http://" : ""), $src);
    }
    private function replaceHttps($src) {
        return $this->replaceHttp($src);
    }
    private function replaceFtp($src) {
        return $this->replaceHttp($src);
    }
    private function replaceSftp($src) {
        return $this->replaceHttp($src);
    }
    private function replaceFtps($src) {
        return $this->replaceHttp($src);
    }
    private function replaceMailto($src) {
        $regex = "/^(?:mailto:)?({$this->uriRegexParts["mailName"]})@({$this->uriRegexParts["host"]})$/{$this->regexFlags}";
        if (($matches = $this->pregMatch($src, $regex)) === false) {
//            var_dump("{$src} does not fit MAIL");
            return $src;
        }
        $name = $matches[1];
        $host = $matches[2];
        $needle = "{$name}@{$host}";
        return $this->replace($needle, "mailto:", $src);
    }
    private function pregMatch($src, $regex) {
        $matches = [];
        preg_match($regex, $src, $matches);
        return !empty($matches) ? $matches : false;
    }
    private function replace($needle, $protocol, $haystack) {
        $replaced = "<a href=\"{$protocol}{$needle}\">{$needle}</a>";
        return str_replace($needle, $replaced, $haystack);
    }
    /**
     * Перебирает все возможные функции замены
     * 
     * На случай, если протокол не указан
     * 
     * @param string $src
     * @return string
     */
    private function replaceUnknown($src) {
        foreach (explode("|", $this->protocols) as $p) {
//            var_dump("Try handle {$src} via protocol: {$p}");
            $res = $this->callReplacer($p, $src);
            if ($res !== $src)
                return $res;
        }
        return $src;
    }
    private function callReplacer($protocol, $src) {
        $methodName = "replace" . ucfirst($protocol);
        if (!method_exists($this, $methodName))
            return $src;
        
        return call_user_func("self::" . $methodName, $src);
    }
}

$test = new URISeekAndReplace();
$test->test();