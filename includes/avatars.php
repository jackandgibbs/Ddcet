<?php
/**
 * Fun SVG avatar library + helpers.
 *
 * Each avatar is a self-contained 100×100 SVG (a filled background so it crops
 * cleanly to a circle). We store a chosen avatar as a data-URI in
 * students.avatar_url, so every existing `<img src="avatar_url">` across the app
 * renders it with no other changes — no new column, no per-page edits.
 */

/** key => ['label' => display name, 'svg' => raw SVG markup]. */
function avatarLibrary(): array {
    return [
        'robot' => ['label' => 'Robot', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#1e293b"/><line x1="50" y1="32" x2="50" y2="18" stroke="#cbd5e1" stroke-width="3"/><circle cx="50" cy="15" r="5" fill="#f0a500"/><rect x="28" y="32" width="44" height="40" rx="8" fill="#94a3b8"/><circle cx="42" cy="50" r="6" fill="#0ea5e9"/><circle cx="58" cy="50" r="6" fill="#0ea5e9"/><rect x="40" y="62" width="20" height="5" rx="2" fill="#475569"/></svg>'],

        'alien' => ['label' => 'Alien', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#052e2b"/><ellipse cx="50" cy="52" rx="22" ry="27" fill="#4ade80"/><ellipse cx="42" cy="50" rx="5" ry="9" fill="#0b1020" transform="rotate(-20 42 50)"/><ellipse cx="58" cy="50" rx="5" ry="9" fill="#0b1020" transform="rotate(20 58 50)"/></svg>'],

        'ninja' => ['label' => 'Ninja', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#4338ca"/><circle cx="50" cy="52" r="26" fill="#111827"/><rect x="24" y="44" width="52" height="12" fill="#f3f4f6"/><circle cx="40" cy="50" r="3" fill="#111827"/><circle cx="60" cy="50" r="3" fill="#111827"/></svg>'],

        'cat' => ['label' => 'Cat', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#fb923c"/><polygon points="30,30 40,15 48,32" fill="#f97316"/><polygon points="70,30 60,15 52,32" fill="#f97316"/><circle cx="50" cy="52" r="24" fill="#fdba74"/><circle cx="42" cy="50" r="4" fill="#1f2937"/><circle cx="58" cy="50" r="4" fill="#1f2937"/><polygon points="47,58 53,58 50,63" fill="#ef4444"/></svg>'],

        'panda' => ['label' => 'Panda', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#38bdf8"/><circle cx="34" cy="34" r="9" fill="#111827"/><circle cx="66" cy="34" r="9" fill="#111827"/><circle cx="50" cy="52" r="24" fill="#f9fafb"/><ellipse cx="42" cy="50" rx="6" ry="8" fill="#111827"/><ellipse cx="58" cy="50" rx="6" ry="8" fill="#111827"/><circle cx="42" cy="50" r="2.5" fill="#fff"/><circle cx="58" cy="50" r="2.5" fill="#fff"/><circle cx="50" cy="62" r="3" fill="#111827"/></svg>'],

        'fox' => ['label' => 'Fox', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#9a3412"/><polygon points="30,28 38,14 44,34" fill="#ea580c"/><polygon points="70,28 62,14 56,34" fill="#ea580c"/><polygon points="28,40 72,40 50,70" fill="#f97316"/><polygon points="38,55 62,55 50,72" fill="#fed7aa"/><circle cx="42" cy="48" r="3" fill="#111827"/><circle cx="58" cy="48" r="3" fill="#111827"/><circle cx="50" cy="62" r="3" fill="#111827"/></svg>'],

        'ghost' => ['label' => 'Ghost', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#7c3aed"/><path d="M30 72 V46 a20 20 0 0 1 40 0 V72 l-7 -7 -6 7 -7 -7 -6 7 -8 -7Z" fill="#f9fafb"/><circle cx="43" cy="48" r="4" fill="#111827"/><circle cx="57" cy="48" r="4" fill="#111827"/></svg>'],

        'monster' => ['label' => 'Monster', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#115e59"/><polygon points="35,30 40,18 45,30" fill="#22d3ee"/><polygon points="65,30 60,18 55,30" fill="#22d3ee"/><circle cx="50" cy="54" r="24" fill="#2dd4bf"/><circle cx="50" cy="50" r="11" fill="#fff"/><circle cx="50" cy="50" r="5" fill="#111827"/><path d="M40 65 q10 8 20 0" stroke="#0f766e" stroke-width="3" fill="none"/></svg>'],

        'pirate' => ['label' => 'Pirate', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#374151"/><circle cx="50" cy="54" r="24" fill="#f3f4f6"/><path d="M24 42 q26 -16 52 0 l-5 7 -42 0Z" fill="#dc2626"/><circle cx="42" cy="54" r="5" fill="#111827"/><circle cx="58" cy="54" r="5" fill="#111827"/><polygon points="48,60 52,60 50,65" fill="#9ca3af"/><rect x="43" y="68" width="3" height="4" fill="#111827"/><rect x="48.5" y="68" width="3" height="4" fill="#111827"/><rect x="54" y="68" width="3" height="4" fill="#111827"/></svg>'],

        'astronaut' => ['label' => 'Astronaut', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#1d4ed8"/><circle cx="50" cy="50" r="27" fill="#e5e7eb"/><circle cx="50" cy="50" r="19" fill="#111827"/><path d="M40 44 a13 13 0 0 1 13 -7" stroke="#60a5fa" stroke-width="3" fill="none"/></svg>'],

        'dino' => ['label' => 'Dino', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#15803d"/><circle cx="50" cy="52" r="24" fill="#4ade80"/><polygon points="40,28 44,18 48,28" fill="#16a34a"/><polygon points="52,28 56,18 60,28" fill="#16a34a"/><circle cx="42" cy="50" r="4" fill="#111827"/><circle cx="58" cy="50" r="4" fill="#111827"/><path d="M40 62 h20" stroke="#111827" stroke-width="3"/><rect x="44" y="62" width="3" height="4" fill="#fff"/><rect x="53" y="62" width="3" height="4" fill="#fff"/></svg>'],

        'unicorn' => ['label' => 'Unicorn', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#ec4899"/><circle cx="50" cy="54" r="23" fill="#fbcfe8"/><polygon points="50,14 45,34 55,34" fill="#f59e0b"/><circle cx="42" cy="52" r="3.5" fill="#111827"/><circle cx="58" cy="52" r="3.5" fill="#111827"/><path d="M44 62 q6 5 12 0" stroke="#be185d" stroke-width="3" fill="none"/></svg>'],

        'frog' => ['label' => 'Frog', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#65a30d"/><circle cx="38" cy="38" r="10" fill="#84cc16"/><circle cx="62" cy="38" r="10" fill="#84cc16"/><circle cx="38" cy="38" r="4" fill="#111827"/><circle cx="62" cy="38" r="4" fill="#111827"/><circle cx="50" cy="58" r="22" fill="#84cc16"/><path d="M36 60 q14 12 28 0" stroke="#3f6212" stroke-width="3" fill="none"/></svg>'],

        'owl' => ['label' => 'Owl', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#6d28d9"/><circle cx="50" cy="52" r="24" fill="#a78bfa"/><circle cx="41" cy="48" r="9" fill="#fff"/><circle cx="59" cy="48" r="9" fill="#fff"/><circle cx="41" cy="48" r="4" fill="#111827"/><circle cx="59" cy="48" r="4" fill="#111827"/><polygon points="47,54 53,54 50,60" fill="#f59e0b"/></svg>'],

        'bear' => ['label' => 'Bear', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#b45309"/><circle cx="34" cy="34" r="9" fill="#92400e"/><circle cx="66" cy="34" r="9" fill="#92400e"/><circle cx="50" cy="52" r="24" fill="#d97706"/><circle cx="50" cy="58" r="9" fill="#fde68a"/><circle cx="42" cy="48" r="3.5" fill="#111827"/><circle cx="58" cy="48" r="3.5" fill="#111827"/><circle cx="50" cy="55" r="3" fill="#111827"/></svg>'],

        'imp' => ['label' => 'Imp', 'svg' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#b91c1c"/><polygon points="34,30 30,16 42,28" fill="#7f1d1d"/><polygon points="66,30 70,16 58,28" fill="#7f1d1d"/><circle cx="50" cy="54" r="23" fill="#ef4444"/><polygon points="38,50 46,52 38,55" fill="#111827"/><polygon points="62,50 54,52 62,55" fill="#111827"/><path d="M40 62 q10 8 20 0" stroke="#7f1d1d" stroke-width="3" fill="none"/></svg>'],
    ];
}

/** Build the data-URI for an avatar SVG (safe to store in avatar_url / put in src). */
function avatarDataUri(string $svg): string {
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/** key => data-URI, for matching a saved avatar back to its key. */
function avatarDataUris(): array {
    $map = [];
    foreach (avatarLibrary() as $key => $a) $map[$key] = avatarDataUri($a['svg']);
    return $map;
}
