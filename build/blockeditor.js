(()=>{"use strict";var e,t={989:()=>{const e=window.React,t=window.wp.plugins,a=window.wp.editPost,r=window.wp.components,o=window.wp.element,s=window.wp.data,n=window.wp.i18n;(0,t.registerPlugin)("rrze-autoshare-panel",{render:()=>{const t=(0,s.useSelect)((e=>e("core/editor").getEditedPostAttribute("meta"))),{editPost:l}=(0,s.useDispatch)("core/editor"),d=autoshareObject.blueskyConnected,i=autoshareObject.blueskyEnabled,h=autoshareObject.blueskyPublished,u=autoshareObject.mastodonConnected,c=autoshareObject.mastodonEnabled,b=autoshareObject.mastodonPublished,_=autoshareObject.twitterConnected,p=autoshareObject.twitterEnabled,w=autoshareObject.twitterPublished,[m,z]=(0,o.useState)(d&&i&&!h),[k,v]=(0,o.useState)(u&&c&&!b),[O,g]=(0,o.useState)(_&&p&&!w);(0,o.useEffect)((()=>{m!==!!t.rrze_autoshare_bluesky_enabled&&wp.data.dispatch("core/editor").editPost({meta:{rrze_autoshare_bluesky_enabled:m}}),k!==!!t.rrze_autoshare_mastodon_enabled&&wp.data.dispatch("core/editor").editPost({meta:{rrze_autoshare_mastodon_enabled:k}}),O!==!!t.rrze_autoshare_twitter_enabled&&wp.data.dispatch("core/editor").editPost({meta:{rrze_autoshare_twitter_enabled:O}})}),[m,k,O]);let f=(0,n.__)("Share on Bluesky","rrze-autoshare");d?h&&(f=(0,n.__)("It is published on Bluesky","rrze-autoshare")):f=(0,n.__)("Share on Bluesky is disabled","rrze-autoshare");let C=(0,n.__)("Share on Mastodon","rrze-autoshare");u?b&&(C=(0,n.__)("It is published on Mastodon","rrze-autoshare")):C=(0,n.__)("Share on Mastodon is disabled","rrze-autoshare");let E=(0,n.__)("Share on X (Twitter)","rrze-autoshare");_?w&&(E=(0,n.__)("It is published on X","rrze-autoshare")):E=(0,n.__)("Share on X is disabled","rrze-autoshare");const P=d&&!h?"":"checkbox-control-disabled",j=u&&!b?"":"checkbox-control-disabled",y=_&&!w?"":"checkbox-control-disabled";return(0,e.createElement)(a.PluginDocumentSettingPanel,{name:"rrze-autoshare-panel",title:(0,n.__)("Autoshare","rrze-autoshare"),className:"rrze-autoshare-panel"},(0,e.createElement)("div",{className:P},(0,e.createElement)(r.CheckboxControl,{label:f,checked:m,disabled:!d||h,onChange:e=>z(e)})),(0,e.createElement)("div",{className:j},(0,e.createElement)(r.CheckboxControl,{label:C,checked:k,disabled:!u||b,onChange:e=>v(e)})),(0,e.createElement)("div",{className:y},(0,e.createElement)(r.CheckboxControl,{label:E,checked:O,disabled:!_||w,onChange:e=>g(e)})))}})}},a={};function r(e){var o=a[e];if(void 0!==o)return o.exports;var s=a[e]={exports:{}};return t[e](s,s.exports,r),s.exports}r.m=t,e=[],r.O=(t,a,o,s)=>{if(!a){var n=1/0;for(h=0;h<e.length;h++){for(var[a,o,s]=e[h],l=!0,d=0;d<a.length;d++)(!1&s||n>=s)&&Object.keys(r.O).every((e=>r.O[e](a[d])))?a.splice(d--,1):(l=!1,s<n&&(n=s));if(l){e.splice(h--,1);var i=o();void 0!==i&&(t=i)}}return t}s=s||0;for(var h=e.length;h>0&&e[h-1][2]>s;h--)e[h]=e[h-1];e[h]=[a,o,s]},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={133:0,82:0};r.O.j=t=>0===e[t];var t=(t,a)=>{var o,s,[n,l,d]=a,i=0;if(n.some((t=>0!==e[t]))){for(o in l)r.o(l,o)&&(r.m[o]=l[o]);if(d)var h=d(r)}for(t&&t(a);i<n.length;i++)s=n[i],r.o(e,s)&&e[s]&&e[s][0](),e[s]=0;return r.O(h)},a=globalThis.webpackChunk_rrze_rrze_autoshare=globalThis.webpackChunk_rrze_rrze_autoshare||[];a.forEach(t.bind(null,0)),a.push=t.bind(null,a.push.bind(a))})();var o=r.O(void 0,[82],(()=>r(989)));o=r.O(o)})();