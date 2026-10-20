<?php
/**
 * Ok, glad you are here
 * first we get a config instance, and set the settings
 * $config = HTMLPurifier_Config::createDefault();
 * $config->set('Core.Encoding', $this->config->get('purifier.encoding'));
 * $config->set('Cache.SerializerPath', $this->config->get('purifier.cachePath'));
 * if ( ! $this->config->get('purifier.finalize')) {
 *     $config->autoFinalize = false;
 * }
 * $config->loadArray($this->getConfig());
 *
 * You must NOT delete the default settings
 * anything in settings should be compacted with params that needed to instance HTMLPurifier_Config.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding'           => 'UTF-8',
    'finalize'           => true,
    'ignoreNonStrings'   => false,
    'cachePath'          => storage_path('app/purifier'),
    'cacheFileMode'      => 0755,
    'settings'      => [
        'default' => [
            'HTML.Doctype'             => 'HTML 4.01 Transitional',
            // Doit couvrir tout ce que TiptapEditor peut produire (voir src/features/admin/components/TiptapEditor.tsx),
            // sinon le contenu perd silencieusement titres/tableaux/soulignement/etc. à chaque sauvegarde.
            'HTML.Allowed'             => 'p[style],div[style|data-align|data-width|data-src|data-youtube-wrapper|data-video-wrapper],b,strong,i,em,u,s,strike,a[href|title|target|rel],ul,ol,li,'
                . 'h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],br,hr,span[style],'
                . 'img[width|height|alt|src|style|title|data-align|loading|decoding],blockquote[style],pre,code,sub,sup,mark[style|data-color],'
                . 'table[style],thead,tbody,tfoot,tr,td[colspan|rowspan|colwidth|style],th[colspan|rowspan|colwidth|style],'
                . 'iframe[src|allowfullscreen|allow|style|width|height|frameborder|sandbox|referrerpolicy|loading],'
                . 'video[src|controls|style|width|height|preload]',
            'CSS.AllowedProperties'    => 'font,font-size,font-weight,font-style,font-family,text-decoration,'
                . 'padding-left,color,background-color,text-align,width,height,max-width,line-height,margin,margin-bottom,'
                . 'aspect-ratio,border',
            'URI.AllowedSchemes'       => [
                'http' => true,
                'https' => true,
                'mailto' => true,
                'tel' => true,
            ],
            'Filter.Custom'            => [
                new \App\Support\LegacyMediaAlignmentFilter(),
            ],
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty'   => false,
            'HTML.SafeIframe'          => 'true',
            'URI.SafeIframeRegexp'     => '%^(http://|https://|//)(www.youtube.com/embed/|player.vimeo.com/video/)%',
        ],
        'test'    => [
            'Attr.EnableID' => 'true',
        ],
        "youtube" => [
            "HTML.SafeIframe"      => 'true',
            "URI.SafeIframeRegexp" => "%^(http://|https://|//)(www.youtube.com/embed/|player.vimeo.com/video/)%",
        ],
        'custom_definition' => [
            'id'  => 'html5-definitions',
            // Incrémenter à chaque évolution de la définition afin que les
            // installations ne réutilisent jamais un cache HTMLPurifier obsolète.
            'rev' => 4,
            'debug' => false,
            'elements' => [
                // http://developers.whatwg.org/sections.html
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],
				
				// Content model actually excludes several tags, not modelled here
                ['address', 'Block', 'Flow', 'Common'],
                ['hgroup', 'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6', 'Common'],
				
				// http://developers.whatwg.org/grouping-content.html
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
				
				// http://developers.whatwg.org/the-video-element.html#the-video-element
                ['video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
                    'src' => 'URI',
					'type' => 'Text',
					'width' => 'Length',
					'height' => 'Length',
					'poster' => 'URI',
					'preload' => 'Enum#auto,metadata,none',
					'controls' => 'Bool',
                ]],
                ['source', 'Block', 'Flow', 'Common', [
					'src' => 'URI',
					'type' => 'Text',
                ]],

				// http://developers.whatwg.org/text-level-semantics.html
                ['s',    'Inline', 'Inline', 'Common'],
                ['var',  'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                // data-color doit être déclaré avec l'élément : Mews ajoute les
                // attributs custom_definition avant les éléments et planterait
                // si on le déclarait plus bas dans "attributes".
                ['mark', 'Inline', 'Inline', 'Common', [
                    'data-color' => 'Text',
                ]],
                ['wbr',  'Inline', 'Empty', 'Core'],
				
				// http://developers.whatwg.org/edits.html
                ['ins', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
                ['del', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['table', 'height', 'Text'],
                ['td', 'border', 'Text'],
                ['td', 'colwidth', \App\Support\TiptapColumnWidthAttribute::class],
                ['th', 'border', 'Text'],
                ['th', 'colwidth', \App\Support\TiptapColumnWidthAttribute::class],
                ['tr', 'width', 'Text'],
                ['tr', 'height', 'Text'],
                ['tr', 'border', 'Text'],
                ['div', 'data-align', 'Text'],
                ['div', 'data-width', 'Text'],
                ['div', 'data-src', 'URI'],
                ['div', 'data-youtube-wrapper', 'Text'],
                ['div', 'data-video-wrapper', 'Text'],
                ['img', 'data-align', 'Text'],
                ['img', 'loading', 'Enum#lazy,eager'],
                ['img', 'decoding', 'Enum#async,sync,auto'],
                ['iframe', 'allow', 'Text'],
                ['iframe', 'sandbox', 'Text'],
                ['iframe', 'referrerpolicy', 'Enum#no-referrer,strict-origin-when-cross-origin'],
                ['iframe', 'loading', 'Enum#lazy,eager'],
            ],
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_parent,_top'],
        ],
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],
    ],

];
