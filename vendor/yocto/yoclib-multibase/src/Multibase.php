<?php
namespace YOCLIB\Multiformats\Multibase;

use InvalidArgumentException;

class Multibase{

    public const IDENTITY = "\0";
    /*EXPERIMENTAL*/public const BASE2 = '0';
    /*DRAFT*/public const BASE8 = '7';
    /*DRAFT*/public const BASE10 = '9';
    public const BASE16 = 'f';
    public const BASE16UPPER = 'F';
    /*EXPERIMENTAL*/public const BASE32HEX = 'v';
    /*EXPERIMENTAL*/public const BASE32HEXUPPER = 'V';
    /*EXPERIMENTAL*/public const BASE32HEXPAD = 't';
    /*EXPERIMENTAL*/public const BASE32HEXPADUPPER = 'T';
    public const BASE32 = 'b';
    public const BASE32UPPER = 'B';
    /*DRAFT*/public const BASE32PAD = 'c';
    /*DRAFT*/public const BASE32PADUPPER = 'C';
    /*DRAFT*/public const BASE32Z = 'h';
    /*DRAFT*/public const BASE36 = 'k';
    /*DRAFT*/public const BASE36UPPER = 'K';
    /*DRAFT*/public const BASE45 = 'R';
    public const BASE58BTC = 'z';
    /*EXPERIMENTAL*/public const BASE58FLICKR = 'Z';
    public const BASE64 = 'm';
    /*EXPERIMENTAL*/public const BASE64PAD = 'M';
    public const BASE64URL = 'u';
    public const BASE64URLPAD = 'U';
    /*EXPERIMENTAL*/public const PROQUINT = 'p';
    /*EXPERIMENTAL*/public const BASE256EMOJI = '🚀';

    private const ALPHABET32 = 'abcdefghijklmnopqrstuvwxyz234567=';
    private const ALPHABET32_HEX = '0123456789abcdefghijklmnopqrstuv=';
    private const ALPHABET32_ZOOKO = 'ybndrfg8ejkmcpqxot1uwisza345h769=';

    private const ALPHABET45 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    private const ALPHABET58_BITCOIN = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    private const ALPHABET58_FLICKR = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const ALPHABET_PROQUINT_CONSONANTS = 'bdfghjklmnprstvz';
    private const ALPHABET_PROQUINT_VOWELS = 'aiou';

    private const ALPHABET256 = '🚀🪐☄🛰🌌🌑🌒🌓🌔🌕🌖🌗🌘🌍🌏🌎🐉☀💻🖥💾💿😂❤😍🤣😊🙏💕😭😘👍😅👏😁🔥🥰💔💖💙😢🤔😆🙄💪😉☺👌🤗💜😔😎😇🌹🤦🎉💞✌✨🤷😱😌🌸🙌😋💗💚😏💛🙂💓🤩😄😀🖤😃💯🙈👇🎶😒🤭❣😜💋👀😪😑💥🙋😞😩😡🤪👊🥳😥🤤👉💃😳✋😚😝😴🌟😬🙃🍀🌷😻😓⭐✅🥺🌈😈🤘💦✔😣🏃💐☹🎊💘😠☝😕🌺🎂🌻😐🖕💝🙊😹🗣💫💀👑🎵🤞😛🔴😤🌼😫⚽🤙☕🏆🤫👈😮🙆🍻🍃🐶💁😲🌿🧡🎁⚡🌞🎈❌✊👋😰🤨😶🤝🚶💰🍓💢🤟🙁🚨💨🤬✈🎀🍺🤓😙💟🌱😖👶🥴▶➡❓💎💸⬇😨🌚🦋😷🕺⚠🙅😟😵👎🤲🤠🤧📌🔵💅🧐🐾🍒😗🤑🌊🤯🐷☎💧😯💆👆🎤🙇🍑❄🌴💣🐸💌📍🥀🤢👅💡💩👐📸👻🤐🤮🎼🥵🚩🍎🍊👼💍📣🥂';
    
    /**
     * @param string $data
     * @param string|null $code
     * @return string
     */
    public static function decode(string $data,?string $code=null): string{
        if($code==null){
            $data = mb_str_split($data);
            $code = array_shift($data);
            $data = implode($data);
        }
        switch($code){
            case self::IDENTITY:{
                $decoded = $data;
                break;
            }
            case self::BASE2:{
                $decoded = Base2::decode($data);
                break;
            }
            case self::BASE8:{
                $decoded = Base8::decode($data);
                break;
            }
            case self::BASE10:{
                $decoded = Base10::decode($data);
                break;
            }
            case self::BASE16:{
                $decoded = Base16::decode($data);
                break;
            }
            case self::BASE16UPPER:{
                $decoded = Base16::decode(strtolower($data));
                break;
            }
            case self::BASE32HEX:
            case self::BASE32HEXPAD:{
                $decoded = Base32::decode($data,self::ALPHABET32_HEX);
                break;
            }
            case self::BASE32HEXUPPER:
            case self::BASE32HEXPADUPPER:{
                $decoded = Base32::decode(strtolower($data),self::ALPHABET32_HEX);
                break;
            }
            case self::BASE32:
            case self::BASE32PAD:{
                $decoded = Base32::decode($data,self::ALPHABET32);
                break;
            }
            case self::BASE32UPPER:
            case self::BASE32PADUPPER:{
                $decoded = Base32::decode(strtolower($data),self::ALPHABET32);
                break;
            }
            case self::BASE32Z:{
                $decoded = Base32::decode($data,self::ALPHABET32_ZOOKO);
                break;
            }
            case self::BASE36:{
                $decoded = Base36::decode($data);
                break;
            }
            case self::BASE36UPPER:{
                $decoded = Base36::decode(strtolower($data));
                break;
            }
            case self::BASE45:{
                $decoded = Base45::decode($data,self::ALPHABET45);
                break;
            }
            case self::BASE58BTC:{
                $decoded = Base58::decode($data,self::ALPHABET58_BITCOIN);
                break;
            }
            case self::BASE58FLICKR:{
                $decoded = Base58::decode($data,self::ALPHABET58_FLICKR);
                break;
            }
            case self::BASE64:
            case self::BASE64PAD:{
                $decoded = Base64::decode($data);
                break;
            }
            case self::BASE64URL:
            case self::BASE64URLPAD:{
                $decoded = Base64::decode(str_replace(['-','_'],['+','/'],$data));
                break;
            }
            case self::PROQUINT:{
                $decoded = Proquint::decode($data,self::ALPHABET_PROQUINT_CONSONANTS,self::ALPHABET_PROQUINT_VOWELS);
                break;
            }
            case self::BASE256EMOJI:{
                $decoded = Base256Emoji::decode($data,self::ALPHABET256);
                break;
            }
            default:{
                throw new InvalidArgumentException('Unsupported base decoding: '.$code);
            }
        }
        return $decoded;
    }

    /**
     * @param string $code
     * @param string $data
     * @param bool $addCodePrefix
     * @return string|null
     */
    public static function encode(string $code,string $data,bool $addCodePrefix=true): ?string{
        switch($code){
            case self::IDENTITY:{
                $encoded = $data;
                break;
            }
            case self::BASE2:{
                $encoded = Base2::encode($data);
                break;
            }
            case self::BASE8:{
                $encoded = Base8::encode($data);
                break;
            }
            case self::BASE10:{
                $encoded = Base10::encode($data);
                break;
            }
            case self::BASE16:{
                $encoded = Base16::encode($data);
                break;
            }
            case self::BASE16UPPER:{
                $encoded = strtoupper(Base16::encode($data));
                break;
            }
            case self::BASE32HEX:{
                $encoded = str_replace('=','',Base32::encode($data,self::ALPHABET32_HEX));
                break;
            }
            case self::BASE32HEXUPPER:{
                $encoded = str_replace('=','',strtoupper(Base32::encode($data,self::ALPHABET32_HEX)));
                break;
            }
            case self::BASE32HEXPAD:{
                $encoded = Base32::encode($data,self::ALPHABET32_HEX);
                break;
            }
            case self::BASE32HEXPADUPPER:{
                $encoded = strtoupper(Base32::encode($data,self::ALPHABET32_HEX));
                break;
            }
            case self::BASE32:{
                $encoded = str_replace('=','',Base32::encode($data,self::ALPHABET32));
                break;
            }
            case self::BASE32UPPER:{
                $encoded = str_replace('=','',strtoupper(Base32::encode($data,self::ALPHABET32)));
                break;
            }
            case self::BASE32PAD:{
                $encoded = Base32::encode($data,self::ALPHABET32);
                break;
            }
            case self::BASE32PADUPPER:{
                $encoded = strtoupper(Base32::encode($data,self::ALPHABET32));
                break;
            }
            case self::BASE32Z:{
                $encoded = str_replace('=','',Base32::encode($data,self::ALPHABET32_ZOOKO));
                break;
            }
            case self::BASE36:{
                $encoded = Base36::encode($data);
                break;
            }
            case self::BASE36UPPER:{
                $encoded = strtoupper(Base36::encode($data));
                break;
            }
            case self::BASE45:{
                $encoded = Base45::encode($data,self::ALPHABET45);
                break;
            }
            case self::BASE58BTC:{
                $encoded = Base58::encode($data,self::ALPHABET58_BITCOIN);
                break;
            }
            case self::BASE58FLICKR:{
                $encoded = Base58::encode($data,self::ALPHABET58_FLICKR);
                break;
            }
            case self::BASE64:{
                $encoded = str_replace('=','',Base64::encode($data));
                break;
            }
            case self::BASE64PAD:{
                $encoded = Base64::encode($data);
                break;
            }
            case self::BASE64URL:{
                $encoded = str_replace('=','',str_replace(['+','/'],['-','_'],Base64::encode($data)));
                break;
            }
            case self::BASE64URLPAD:{
                $encoded = str_replace(['+','/'],['-','_'],Base64::encode($data));
                break;
            }
            case self::PROQUINT:{
                $encoded = Proquint::encode($data,self::ALPHABET_PROQUINT_CONSONANTS,self::ALPHABET_PROQUINT_VOWELS);
                break;
            }
            case self::BASE256EMOJI:{
                $encoded = Base256Emoji::encode($data,self::ALPHABET256);
                break;
            }
            default:{
                throw new InvalidArgumentException('Unsupported base encoding: '.$code);
            }
        }
        if($addCodePrefix){
            $encoded = $code.$encoded;
        }
        return $encoded;
    }

}