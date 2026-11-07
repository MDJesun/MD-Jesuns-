<?php
// not a page: block direct visits
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
/*
 * Content schema: every editable field on the website.
 * The Admin Panel builds its forms from this file and the server uses it
 * to check everything before saving, so the two can never disagree.
 */

function mdj_themes(): array
{
    return [
        'midnight' => ['label' => 'Midnight Blue',   'desc' => 'Cool electric blue on deep navy-black.', 'swatch' => ['#2EB7F6', '#BFEAFF', '#0A6FA8', '#04060B']],
        'orange'   => ['label' => 'Charcoal Orange', 'desc' => 'Warm amber light on charcoal.',          'swatch' => ['#FF7A1A', '#FFD4B2', '#A04004', '#0A0908']],
        'purple'   => ['label' => 'Slate Purple',    'desc' => 'Violet glow on slate-black.',            'swatch' => ['#9B7BFF', '#DED3FF', '#5C3ED2', '#07060D']],
        'emerald'  => ['label' => 'Emerald Dark',    'desc' => 'Fresh emerald on dark green-black.',     'swatch' => ['#22D3A6', '#B8F5E4', '#086850', '#040A08']],
    ];
}

/** sections of the Home page below the hero, in their default order */
function mdj_home_sections(): array
{
    return [
        'about' => 'Who’s behind the edit (about you)',
        'work' => 'Featured work (3D carousel)',
        'reel' => 'Showreel',
        'marquee' => 'Moving banner',
        'services' => 'Services preview',
        'quotes' => 'Testimonials (client videos + quotes)',
        'cta' => 'Closing call to action',
    ];
}

function mdj_icons(): array
{
    return [
        'edit' => 'Video editing', 'motion' => 'Motion graphics', 'color' => 'Colour grading',
        'shorts' => 'Short-form / phone', 'sound' => 'Sound design', 'camera' => 'Camera',
        'spark' => 'Sparkle / effects', 'film' => 'Film reel',
    ];
}

function mdj_schema(): array
{
    $t = function (string $k, string $label, int $max = 120, string $help = '') {
        return ['k' => $k, 'label' => $label, 'type' => 'text', 'max' => $max, 'help' => $help];
    };
    $ta = function (string $k, string $label, int $max = 1500, string $help = '') {
        return ['k' => $k, 'label' => $label, 'type' => 'textarea', 'max' => $max, 'help' => $help];
    };
    $img = function (string $k, string $label, string $help = '') {
        return ['k' => $k, 'label' => $label, 'type' => 'image', 'help' => $help];
    };
    $url = function (string $k, string $label, string $help = '') {
        return ['k' => $k, 'label' => $label, 'type' => 'url', 'help' => $help];
    };
    $vid = function (string $k, string $label, string $help = '') {
        return ['k' => $k, 'label' => $label, 'type' => 'video', 'help' => $help];
    };
    $themeOpts = [];
    foreach (mdj_themes() as $id => $th) {
        $themeOpts[$id] = $th['label'];
    }

    return [
        /* ------------------------------------------------------------ pages */
        'home' => [
            'label' => 'Home page', 'group' => 'Pages', 'icon' => 'home', 'page' => 'index.php',
            'desc' => 'Your hero, the “Who’s behind the edit” section, featured work, showreel, services preview, testimonials and the closing call to action.',
            'fields' => [
                ['type' => 'heading', 'label' => 'Order of the sections'],
                ['k' => 'sections', 'label' => 'Sections below the hero, from top to bottom', 'type' => 'order', 'options' => mdj_home_sections(),
                    'help' => 'Use the arrows to move a section up or down. Switch a section off to hide it. The first section slides up over the hero.'],
                ['k' => 'numbered', 'label' => 'Number the sections automatically (01, 02, 03…)', 'type' => 'toggle'],
                ['type' => 'heading', 'label' => 'Hero (top of the page)'],
                $t('heroEyebrow', 'Small line above your name', 60),
                $t('bigName', 'Big outlined name behind your photo', 14, 'Short works best, for example your first name.'),
                $img('heroImage', 'Your profile photo (hero)', 'Best: a PNG with a transparent background, about 1800 × 1240 px. Edges fade into the page automatically.'),
                $t('availability', 'Availability line', 60),
                $t('headline1', 'Headline, line 1', 40),
                $t('headline2', 'Headline, line 2 (coloured)', 40),
                $t('ctaPrimary', 'Main button text', 30),
                $t('ctaSecondary', 'Showreel button text', 30),
                $t('nowEditingLabel', 'Small card label', 30),
                $t('nowEditing', 'Small card: what you are editing now', 50),
                $t('badgeValue', 'Highlight number (e.g. 48h)', 12),
                $t('badgeText', 'Highlight text', 60),
                ['type' => 'heading', 'label' => 'Who’s behind the edit (about you)'],
                ['type' => 'note', 'label' => 'Your name, bio, hologram photo, stats and toolkit come from the About page, so you only edit them in one place.'],
                $t('aboutEyebrow', 'Small label', 40, 'The number (01, 02…) is added automatically.'),
                $t('aboutButton', 'Button to the About page', 30),
                ['type' => 'heading', 'label' => 'Featured work (3D carousel)'],
                ['type' => 'note', 'label' => 'Choose which projects appear here in Portfolio projects → “Show on Home page”.'],
                $t('workEyebrow', 'Small label', 40, 'The number (01, 02…) is added automatically.'),
                $t('workTitle1', 'Title, line 1', 40),
                $t('workTitle2', 'Title, line 2 (coloured)', 40),
                $t('workButton', 'Button to the full portfolio', 40),
                ['type' => 'heading', 'label' => 'Showreel'],
                $t('reelEyebrow', 'Small label', 40, 'The number (01, 02…) is added automatically.'),
                $t('reelTitle1', 'Title, line 1', 40),
                $t('reelTitle2', 'Title, line 2 (coloured)', 40),
                $ta('reelText', 'Short description', 300),
                $img('reelCover', 'Showreel cover image', 'Wide 16:9 image, about 1600 × 900 px.'),
                $url('reelVideo', 'Showreel video link', 'Paste a YouTube or Vimeo link, or a direct .mp4 link. Visitors watch it in a pop-up player.'),
                ['type' => 'heading', 'label' => 'Moving banner'],
                $ta('marquee', 'Words in the moving banner', 400, 'Separate each item with a comma.'),
                ['type' => 'heading', 'label' => 'Services preview'],
                ['type' => 'note', 'label' => 'This section only appears when you have at least one service in Services & prices.'],
                $t('servicesEyebrow', 'Small label', 40, 'The number (01, 02…) is added automatically.'),
                $t('servicesTitle1', 'Title, line 1', 40),
                $t('servicesTitle2', 'Title, line 2 (coloured)', 40),
                $t('servicesButton', 'Button to the Services page', 40),
                ['type' => 'heading', 'label' => 'Testimonials'],
                ['type' => 'note', 'label' => 'Client videos come from Video testimonials (switch on “Show on Home page”). Written quotes come from Written testimonials.'],
                $t('quotesEyebrow', 'Small label', 40, 'The number (01, 02…) is added automatically.'),
                ['k' => 'quotesVideos', 'label' => 'Show client videos in this section', 'type' => 'toggle'],
                $t('quotesButton', 'Button to the Testimonials page', 40, 'Leave empty to hide the button.'),
                ['type' => 'heading', 'label' => 'Closing call to action'],
                $t('ctaEyebrow', 'Small label', 40, 'The number (01, 02…) is added automatically.'),
                $t('ctaTitle1', 'Title, line 1', 40),
                $t('ctaTitle2', 'Title, line 2 (coloured)', 40),
                $ta('ctaText', 'Text', 300),
                $t('ctaButton', 'Button text', 30),
            ],
        ],
        'portfolio' => [
            'label' => 'Portfolio page', 'group' => 'Pages', 'icon' => 'grid', 'page' => 'portfolio.php',
            'desc' => 'The heading and text of the full Portfolio page. Projects themselves are edited in Portfolio projects.',
            'fields' => [
                $t('eyebrow', 'Small label', 40),
                $t('title1', 'Title, line 1', 40),
                $t('title2', 'Title, line 2 (coloured)', 40),
                $ta('intro', 'Intro text', 400),
                $t('allLabel', 'Filter button for “all projects”', 20),
                $t('emptyText', 'Text when a filter has no projects', 120),
                $t('ctaTitle', 'Closing line', 80),
                $t('ctaButton', 'Closing button text', 30),
            ],
        ],
        'about' => [
            'label' => 'About page', 'group' => 'Pages', 'icon' => 'user', 'page' => 'about.php',
            'desc' => 'Your bio, hologram photo, stats, toolkit and the Awards heading.',
            'fields' => [
                $t('eyebrow', 'Small label', 40),
                $t('title1', 'Title, line 1', 40),
                $t('title2', 'Title, line 2 (coloured)', 40),
                $ta('bio', 'Your bio', 1200, 'Leave an empty line between paragraphs.'),
                $img('photo', 'Your photo (hologram)', 'Best: a PNG with a transparent background, about 700 × 920 px.'),
                $t('tagSubject', 'Hologram label: name', 40),
                $t('tagRole', 'Hologram label: role', 40),
                $t('tagStatus', 'Hologram label: status', 30),
                ['k' => 'stats', 'label' => 'Stats', 'type' => 'list', 'max' => 6, 'itemTitle' => 'label', 'addLabel' => 'Add stat',
                    'fields' => [$t('value', 'Number (e.g. 120+)', 12), $t('label', 'Label', 30)]],
                ['k' => 'tools', 'label' => 'Toolkit', 'type' => 'list', 'max' => 12, 'itemTitle' => 'name', 'addLabel' => 'Add tool',
                    'fields' => [$t('short', 'Short badge (e.g. Ae)', 4), $t('name', 'Tool name', 40)]],
                $t('workButton', 'Button to your work', 40),
                ['type' => 'heading', 'label' => 'Awards & achievements heading'],
                ['type' => 'note', 'label' => 'Add or remove the awards themselves in Awards & achievements.'],
                $t('awardsEyebrow', 'Small label', 40),
                $t('awardsTitle1', 'Title, line 1', 40),
                $t('awardsTitle2', 'Title, line 2 (coloured)', 40),
                $ta('awardsIntro', 'Intro text', 300),
            ],
        ],
        'servicesPage' => [
            'label' => 'Services page', 'group' => 'Pages', 'icon' => 'layers', 'page' => 'services.php',
            'desc' => 'The heading, price note and process heading. Services and prices are edited in Services & prices.',
            'fields' => [
                $t('eyebrow', 'Small label', 40),
                $t('title1', 'Title, line 1', 40),
                $t('title2', 'Title, line 2 (coloured)', 40),
                $ta('intro', 'Intro text', 400),
                $t('currency', 'Currency symbol', 4, 'Shown before every price, e.g. $, €, £ or ৳.'),
                $ta('priceNote', 'Note under the prices', 300),
                $t('quoteButton', 'Button on each service', 30),
                $t('processEyebrow', 'Process: small label', 40),
                $t('processTitle1', 'Process: title, line 1', 40),
                $t('processTitle2', 'Process: title, line 2 (coloured)', 40),
            ],
        ],
        'testimonialsPage' => [
            'label' => 'Testimonials page', 'group' => 'Pages', 'icon' => 'play', 'page' => 'testimonials.php',
            'desc' => 'The page with your client video testimonials. The videos themselves are added in Video testimonials.',
            'fields' => [
                ['type' => 'heading', 'label' => 'Turn the page on or off'],
                ['k' => 'enabled', 'label' => 'Show the Testimonials page on my website', 'type' => 'toggle',
                    'help' => 'Switch off to hide the whole page: it disappears from the menu, the footer and the Home page, and its address sends visitors to your Home page. Your videos and texts are kept, so you can switch it back on any time.'],
                ['k' => 'inMenu', 'label' => 'Show this page in the top menu and footer', 'type' => 'toggle'],
                $t('navLabel', 'Name in the menu', 20),
                ['type' => 'heading', 'label' => 'Page heading'],
                $t('eyebrow', 'Small label', 40),
                $t('title1', 'Title, line 1', 40),
                $t('title2', 'Title, line 2 (coloured)', 40),
                $ta('intro', 'Intro text', 400),
                ['type' => 'heading', 'label' => 'Videos'],
                $t('playLabel', 'Play button text', 30),
                $t('emptyText', 'Text when there are no videos yet', 160),
                ['type' => 'heading', 'label' => 'Written testimonials'],
                ['k' => 'showQuotes', 'label' => 'Also show your written testimonials under the videos', 'type' => 'toggle'],
                $t('quotesEyebrow', 'Small label', 40),
                $t('quotesTitle1', 'Title, line 1', 40),
                $t('quotesTitle2', 'Title, line 2 (coloured)', 40),
                ['type' => 'heading', 'label' => 'Closing call to action'],
                $t('ctaTitle', 'Closing line', 80),
                $t('ctaButton', 'Closing button text', 30),
            ],
        ],
        'contactPage' => [
            'label' => 'Contact page', 'group' => 'Pages', 'icon' => 'mail', 'page' => 'contact.php',
            'desc' => 'Heading, text and every word of the enquiry form. Your email, WhatsApp number and social links are in Contact & social links.',
            'fields' => [
                ['type' => 'heading', 'label' => 'Page heading'],
                $t('eyebrow', 'Small label', 40),
                $t('title1', 'Title, line 1', 40),
                $t('title2', 'Title, line 2 (coloured)', 40),
                $ta('text', 'Intro text', 400),
                ['type' => 'heading', 'label' => 'Enquiry form'],
                $t('formTitle', 'Form title', 60),
                $ta('formIntro', 'Form intro', 300),
                $t('nameLabel', 'Name: label', 40),
                $t('namePlaceholder', 'Name: example text inside the box', 60),
                $t('emailLabel', 'Email: label', 40),
                $t('emailPlaceholder', 'Email: example text inside the box', 60),
                $t('serviceLabel', 'Service: label', 40),
                $t('servicePlaceholder', 'Service: first line of the list', 60),
                $ta('serviceOptions', 'Service choices (one per line)', 800, 'Leave this empty to list the services from Services & prices automatically.'),
                $t('budgetLabel', 'Budget: label', 40),
                $t('budgetPlaceholder', 'Budget: first line of the list', 60),
                $ta('budgetOptions', 'Budget choices (one per line)', 600, 'Leave this empty to show a free text box instead of a list.'),
                $t('messageLabel', 'Message: label', 40),
                $ta('messagePlaceholder', 'Message: example text inside the box', 300),
                $t('formButton', 'Send button text', 30),
                $t('privacyNote', 'Small line under the button', 120),
                ['type' => 'heading', 'label' => 'After someone sends the form'],
                $t('successTitle', 'Title', 40),
                $ta('successMessage', 'Message', 300),
                $t('anotherButton', 'Button to send another message', 40),
            ],
        ],

        /* ---------------------------------------------------------- content */
        'projects' => [
            'label' => 'Portfolio projects', 'group' => 'Content', 'icon' => 'film', 'page' => 'portfolio.php', 'list' => true,
            'max' => 300, 'itemTitle' => 'title', 'addLabel' => 'Add project',
            'desc' => 'Every project on the Portfolio page. Switch on “Show on Home page” to feature it in the Home carousel.',
            'fields' => [
                $t('title', 'Project title', 60),
                $t('category', 'Category (used for the filter buttons)', 30, 'e.g. Commercial, YouTube, Music video, Motion, Short-form.'),
                $t('client', 'Client name', 60),
                $t('runtime', 'Runtime (e.g. 01:24)', 12),
                $t('year', 'Year', 8),
                $t('result', 'One-line result or brief', 120),
                $img('image', 'Thumbnail', 'Portrait image, about 600 × 840 px.'),
                $url('video', 'Video link (optional)', 'YouTube, Vimeo or .mp4 link. If set, clicking the project plays it.'),
                ['k' => 'featured', 'label' => 'Show on Home page', 'type' => 'toggle'],
            ],
        ],
        'services' => [
            'label' => 'Services & prices', 'group' => 'Content', 'icon' => 'tag', 'page' => 'services.php', 'list' => true,
            'max' => 30, 'itemTitle' => 'title', 'addLabel' => 'Add service',
            'desc' => 'Add or remove services and set the price of each one. They appear on the Services page and the Home page.',
            'fields' => [
                $t('title', 'Service name', 50),
                ['k' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => mdj_icons()],
                $ta('text', 'Description', 400),
                $t('tags', 'Tags (separate with commas)', 120),
                $t('pricePrefix', 'Text before the price (e.g. From)', 20),
                $t('price', 'Price (number only, e.g. 150)', 20),
                $t('priceSuffix', 'Text after the price (e.g. per project)', 30),
            ],
        ],
        'awards' => [
            'label' => 'Awards & achievements', 'group' => 'Content', 'icon' => 'award', 'page' => 'about.php#awards', 'list' => true,
            'max' => 60, 'itemTitle' => 'title', 'addLabel' => 'Add award or achievement',
            'desc' => 'Shown on the About page. Add as many as you like, or remove them all to hide the section.',
            'fields' => [
                $t('year', 'Year', 10),
                $t('title', 'Award or achievement', 80),
                $t('issuer', 'Given by / platform', 60),
                $ta('description', 'Short description (optional)', 300),
                $img('image', 'Logo or badge image (optional)', 'Square image works best, about 200 × 200 px.'),
                $url('link', 'Link (optional)', 'For example the award page or certificate.'),
            ],
        ],
        'videoTestimonials' => [
            'label' => 'Video testimonials', 'group' => 'Content', 'icon' => 'play', 'page' => 'testimonials.php', 'list' => true,
            'max' => 60, 'itemTitle' => 'name', 'addLabel' => 'Add video testimonial',
            'desc' => 'Videos of your clients giving feedback. They appear on the Testimonials page. Switch on “Show on Home page” to also show a video in the Home testimonials section.',
            'fields' => [
                $t('name', 'Client name', 50),
                $t('role', 'Role and company', 70),
                $vid('video', 'The video', 'Best: upload it to YouTube (it can be “Unlisted”) or Vimeo and paste the link here. You can also paste a Google Drive link, or upload an MP4 file.'),
                ['k' => 'format', 'label' => 'Video shape', 'type' => 'select', 'options' => ['landscape' => 'Landscape 16:9 (computer / camera)', 'vertical' => 'Vertical 9:16 (filmed on a phone)', 'square' => 'Square 1:1']],
                $img('cover', 'Cover image (optional)', 'For YouTube links the video’s own thumbnail is used automatically. For uploaded videos the first frame is used.'),
                $ta('quote', 'Highlight line from the video (optional)', 220, 'One sentence the client says, shown on the card.'),
                $t('project', 'What you made for them (optional)', 60, 'e.g. YouTube channel edits, Brand film.'),
                $t('duration', 'Length (optional, e.g. 01:12)', 10),
                ['k' => 'stars', 'label' => 'Star rating', 'type' => 'select', 'options' => ['5' => '★★★★★  5 stars', '4' => '★★★★  4 stars', '0' => 'Don’t show stars']],
                ['k' => 'featured', 'label' => 'Show on Home page', 'type' => 'toggle'],
            ],
        ],
        'testimonials' => [
            'label' => 'Written testimonials', 'group' => 'Content', 'icon' => 'quote', 'page' => 'index.php#testimonials', 'list' => true,
            'max' => 30, 'itemTitle' => 'name', 'addLabel' => 'Add testimonial',
            'desc' => 'Written client quotes on the Home page and the Testimonials page.',
            'fields' => [
                $ta('quote', 'Quote', 500),
                $t('name', 'Client name', 50),
                $t('role', 'Role and company', 60),
                $img('avatar', 'Client photo (optional)', 'Square image, about 200 × 200 px.'),
            ],
        ],
        'process' => [
            'label' => 'Work process steps', 'group' => 'Content', 'icon' => 'steps', 'page' => 'services.php#process', 'list' => true,
            'max' => 8, 'itemTitle' => 'title', 'addLabel' => 'Add step',
            'desc' => 'The timeline on the Services page.',
            'fields' => [
                $t('label', 'Small label (e.g. #01 · Day 1)', 30),
                $t('title', 'Step title', 40),
                $ta('text', 'Step text', 200),
            ],
        ],

        /* --------------------------------------------------------- settings */
        'contact' => [
            'label' => 'Contact & social links', 'group' => 'Settings', 'icon' => 'phone', 'page' => 'contact.php',
            'desc' => 'Used on the Contact page, in the footer and on every “Hire me” button.',
            'fields' => [
                ['k' => 'email', 'label' => 'Contact email', 'type' => 'email', 'help' => 'Shown on the site. To get enquiries in your inbox, set up Settings → Email notifications.'],
                ['k' => 'whatsapp', 'label' => 'WhatsApp number', 'type' => 'tel', 'help' => 'With country code, e.g. +880 1712 345678.'],
                $t('whatsappMessage', 'Ready-made WhatsApp message', 160),
                $url('bookingUrl', 'Booking link (optional)', 'e.g. your Calendly link. Leave empty to send people to the contact form.'),
                $t('location', 'Location / availability line', 60),
                $t('responseTime', 'Response time line', 60),
                ['k' => 'socials', 'label' => 'Social media links', 'type' => 'list', 'max' => 12, 'itemTitle' => 'label', 'addLabel' => 'Add social link',
                    'help' => 'An icon is chosen automatically from the link (Instagram, LinkedIn, X, Facebook, YouTube, Behance, Vimeo, TikTok, Dribbble and more).',
                    'fields' => [$t('label', 'Name (e.g. Instagram)', 30), $url('url', 'Link')]],
            ],
        ],
        'settings' => [
            'label' => 'Site settings', 'group' => 'Settings', 'icon' => 'settings', 'page' => 'index.php',
            'desc' => 'Your name, footer line, search engine text, logo, favicon and motion options.',
            'fields' => [
                $t('siteName', 'Your name / brand', 40),
                $t('role', 'Your role', 60),
                $ta('footerText', 'Footer description', 200),
                $t('seoTitle', 'Browser tab and Google title', 70),
                $ta('seoDescription', 'Google description', 170),
                $img('ogImage', 'Share image (when your link is shared)', 'About 1200 × 630 px.'),
                $img('logo', 'Logo image (optional)', 'If empty, the built-in hexagon logo is used. Square PNG or SVG-style logo, about 120 × 120 px.'),
                $img('favicon', 'Browser tab icon (optional)', 'Square PNG, about 64 × 64 px.'),
                ['type' => 'heading', 'label' => 'Motion & effects'],
                ['k' => 'intro', 'label' => 'Play the intro animation on the Home page', 'type' => 'toggle'],
                ['k' => 'cursor', 'label' => 'Custom cursor on desktop', 'type' => 'toggle'],
                ['k' => 'autoplay', 'label' => 'Home carousel plays by itself', 'type' => 'toggle'],
                ['k' => 'transitions', 'label' => 'Cinematic transition between pages', 'type' => 'toggle'],
                ['k' => 'timeline', 'label' => 'Timecode scroll timeline (bottom left, on computers)', 'type' => 'toggle'],
                ['k' => 'theme', 'label' => 'Colour theme', 'type' => 'select', 'options' => $themeOpts, 'hidden' => true],
            ],
        ],
    ];
}

function mdj_defaults(): array
{
    $proj = function ($id, $title, $cat, $time, $img, $featured) {
        return ['id' => $id, 'title' => $title, 'category' => $cat, 'client' => '[Client name]', 'runtime' => $time,
            'year' => '2026', 'result' => '[One-line result or brief]', 'image' => 'assets/img/work/' . $img, 'video' => '', 'featured' => $featured];
    };
    $order = [];
    foreach (array_keys(mdj_home_sections()) as $k) {
        $order[] = ['k' => $k, 'on' => true];
    }
    return [
        'home' => [
            'sections' => $order,
            'numbered' => true,
            'heroEyebrow' => '// Video editor · Motion designer',
            'bigName' => 'JESUN',
            'heroImage' => 'assets/img/hero-jesun.webp',
            'availability' => 'Available for new projects',
            'headline1' => 'Edits that make',
            'headline2' => 'people stay.',
            'ctaPrimary' => 'See my work',
            'ctaSecondary' => 'Showreel',
            'nowEditingLabel' => 'Now editing',
            'nowEditing' => '[Current project name]',
            'badgeValue' => '[48h]',
            'badgeText' => 'first cut, from brief to draft',
            'aboutEyebrow' => 'Who’s behind the edit',
            'aboutButton' => 'More about me',
            'workEyebrow' => 'Selected work',
            'workTitle1' => 'Stories cut to',
            'workTitle2' => 'keep people watching',
            'workButton' => 'View full portfolio',
            'reelEyebrow' => 'Showreel',
            'reelTitle1' => 'Two minutes.',
            'reelTitle2' => 'Every trick I know.',
            'reelText' => 'A fast cut of my favourite edits, transitions and motion work from the past year.',
            'reelCover' => 'assets/img/reel.jpg',
            'reelVideo' => '',
            'marquee' => 'Video editing, Motion graphics, Colour grading, Sound design, Short-form, Brand films, YouTube, Commercials',
            'servicesEyebrow' => 'Services',
            'servicesTitle1' => 'From raw footage',
            'servicesTitle2' => 'to final export',
            'servicesButton' => 'All services & prices',
            'quotesEyebrow' => 'What clients say',
            'quotesVideos' => true,
            'quotesButton' => 'Watch client video testimonials',
            'ctaEyebrow' => 'Let’s talk',
            'ctaTitle1' => 'Got footage?',
            'ctaTitle2' => 'Let’s cut it.',
            'ctaText' => 'Tell me about your project. I reply within 24 hours with ideas, a timeline and a quote.',
            'ctaButton' => 'Book a free call',
        ],
        'portfolio' => [
            'eyebrow' => 'Portfolio',
            'title1' => 'Selected work,',
            'title2' => 'frame by frame',
            'intro' => 'Commercials, YouTube episodes, music videos and motion pieces. Filter by type, and click a project to watch it.',
            'allLabel' => 'All',
            'emptyText' => 'No projects in this category yet.',
            'ctaTitle' => 'Like what you see? Let’s make the next one together.',
            'ctaButton' => 'Start a project',
        ],
        'about' => [
            'eyebrow' => 'About — Who’s behind the edit',
            'title1' => 'Hi, I’m',
            'title2' => 'MD Jesun',
            'bio' => 'I’m a video editor and motion graphics designer working with brands, agencies and creators around the world. I turn raw footage into stories with rhythm and emotion, and give them a finish that looks expensive.',
            'photo' => 'assets/img/holo-jesun.webp',
            'tagSubject' => 'MD Jesun',
            'tagRole' => 'Edit · Motion',
            'tagStatus' => 'Available',
            'stats' => [
                ['id' => 'st1', 'value' => '[X]+', 'label' => 'Years editing'],
                ['id' => 'st2', 'value' => '[000]+', 'label' => 'Projects shipped'],
                ['id' => 'st3', 'value' => '[00]', 'label' => 'Countries'],
            ],
            'tools' => [
                ['id' => 'tl1', 'short' => 'Pr', 'name' => 'Premiere Pro'],
                ['id' => 'tl2', 'short' => 'Ae', 'name' => 'After Effects'],
                ['id' => 'tl3', 'short' => 'Ps', 'name' => 'Photoshop'],
            ],
            'workButton' => 'See selected work',
            'awardsEyebrow' => 'Recognition',
            'awardsTitle1' => 'Awards &',
            'awardsTitle2' => 'achievements',
            'awardsIntro' => 'A few milestones along the way.',
        ],
        'servicesPage' => [
            'eyebrow' => 'Services',
            'title1' => 'From raw footage',
            'title2' => 'to final export',
            'intro' => 'One editor for the whole post-production pipeline, so your project keeps one vision. Every project gets a clear quote before we start.',
            'currency' => '$',
            'priceNote' => 'Starting prices. Final quotes depend on length, footage and deadline.',
            'quoteButton' => 'Request a quote',
            'processEyebrow' => 'Process',
            'processTitle1' => 'A simple timeline,',
            'processTitle2' => 'start to delivery',
        ],
        'testimonialsPage' => [
            'enabled' => true,
            'inMenu' => true,
            'navLabel' => 'Testimonials',
            'eyebrow' => 'Client testimonials',
            'title1' => 'Don’t take my word.',
            'title2' => 'Hear it from them.',
            'intro' => 'Real clients, on camera, talking about what it was like to work together and what the edit did for them.',
            'playLabel' => 'Play testimonial',
            'emptyText' => 'Client video testimonials are coming soon.',
            'showQuotes' => true,
            'quotesEyebrow' => 'In their words',
            'quotesTitle1' => 'More kind words',
            'quotesTitle2' => 'from clients',
            'ctaTitle' => 'Want results like these? Let’s make your project next.',
            'ctaButton' => 'Start a project',
        ],
        'contactPage' => [
            'eyebrow' => 'Contact',
            'title1' => 'Got footage?',
            'title2' => 'Let’s cut it.',
            'text' => 'Tell me about your project. I reply within 24 hours with ideas, a timeline and a quote.',
            'formTitle' => 'Send a project enquiry',
            'formIntro' => 'A few lines are enough: what you need, rough length and your deadline.',
            'nameLabel' => 'Your name',
            'namePlaceholder' => 'Full name',
            'emailLabel' => 'Email',
            'emailPlaceholder' => 'name@company.com',
            'serviceLabel' => 'Service',
            'servicePlaceholder' => 'Select a service',
            'serviceOptions' => "Video editing\nMotion graphics\nColour grading\nShort-form & reels\nYouTube videos\nAds & commercials\nOther — I’ll explain below",
            'budgetLabel' => 'Budget',
            'budgetPlaceholder' => 'Select a budget range',
            'budgetOptions' => "Under $200\n$200 – $500\n$500 – $1,000\n$1,000 – $2,500\n$2,500+\nFlexible — let’s discuss",
            'messageLabel' => 'About your project',
            'messagePlaceholder' => 'What do you need? The type of video, rough length, style references and your deadline.',
            'formButton' => 'Send message',
            'privacyNote' => 'Your details are only used to reply to your enquiry.',
            'successTitle' => 'Message sent',
            'successMessage' => 'Thanks! Your message is on its way. I’ll get back to you within 24 hours.',
            'anotherButton' => 'Send another message',
        ],
        'projects' => [
            $proj('p1', 'Product Launch Film', 'Commercial', '01:24', 'p1.jpg', true),
            $proj('p2', 'Documentary Episode', 'YouTube', '18:42', 'p2.jpg', true),
            $proj('p3', 'Music Video', 'Music video', '03:36', 'p3.jpg', true),
            $proj('p4', 'Brand Logo Reveal', 'Motion', '00:08', 'p4.jpg', true),
            $proj('p5', 'Travel Film', 'Commercial', '02:10', 'p5.jpg', true),
            $proj('p6', 'Fitness Reels Pack', 'Short-form', '00:30', 'p6.jpg', true),
            $proj('p7', 'SaaS Explainer', 'Motion', '01:05', 'p7.jpg', true),
            $proj('p8', 'Creator Vlog Edit', 'YouTube', '12:04', 'p8.jpg', true),
            $proj('p9', 'Event Recap', 'Short-form', '00:45', 'p9.jpg', true),
        ],
        'services' => [
            ['id' => 'sv1', 'title' => 'Video editing', 'icon' => 'edit', 'text' => 'Commercials, brand films, YouTube and documentaries, cut for story and retention.', 'tags' => 'Long-form, Ads, Docs', 'pricePrefix' => 'From', 'price' => '[150]', 'priceSuffix' => 'per project'],
            ['id' => 'sv2', 'title' => 'Motion graphics', 'icon' => 'motion', 'text' => 'Logo reveals, kinetic typography, lower thirds and animated explainers.', 'tags' => 'Titles, Logos, Explainers', 'pricePrefix' => 'From', 'price' => '[120]', 'priceSuffix' => 'per project'],
            ['id' => 'sv3', 'title' => 'Colour grading', 'icon' => 'color', 'text' => 'Cinematic looks and consistent skin tones that make footage feel expensive.', 'tags' => 'Correction, Looks, LUTs', 'pricePrefix' => 'From', 'price' => '[80]', 'priceSuffix' => 'per video'],
            ['id' => 'sv4', 'title' => 'Short-form & reels', 'icon' => 'shorts', 'text' => 'Hook-first vertical edits with captions for TikTok, Reels and Shorts.', 'tags' => '9:16, Captions, Packs', 'pricePrefix' => 'From', 'price' => '[40]', 'priceSuffix' => 'per reel'],
        ],
        'awards' => [
            ['id' => 'aw1', 'year' => '[2025]', 'title' => '[Award or achievement]', 'issuer' => '[Festival, platform or client]', 'description' => '[One line about what it was for.]', 'image' => '', 'link' => ''],
            ['id' => 'aw2', 'year' => '[2024]', 'title' => '[Award or achievement]', 'issuer' => '[Festival, platform or client]', 'description' => '[One line about what it was for.]', 'image' => '', 'link' => ''],
            ['id' => 'aw3', 'year' => '[2023]', 'title' => '[Award or achievement]', 'issuer' => '[Festival, platform or client]', 'description' => '[One line about what it was for.]', 'image' => '', 'link' => ''],
        ],
        'testimonials' => [
            ['id' => 'tm1', 'quote' => '[Client testimonial: one or two sentences about the result you delivered.]', 'name' => '[Client name]', 'role' => '[Role, company]', 'avatar' => ''],
            ['id' => 'tm2', 'quote' => '[Client testimonial: one or two sentences about the result you delivered.]', 'name' => '[Client name]', 'role' => '[Role, company]', 'avatar' => ''],
            ['id' => 'tm3', 'quote' => '[Client testimonial: one or two sentences about the result you delivered.]', 'name' => '[Client name]', 'role' => '[Role, company]', 'avatar' => ''],
        ],
        'videoTestimonials' => [
            ['id' => 'vt1', 'name' => '[Client name]', 'role' => '[Role, company]', 'video' => '', 'format' => 'landscape', 'cover' => 'assets/img/work/p2.jpg', 'quote' => '[One line the client says in the video.]', 'project' => '[What you made for them]', 'duration' => '', 'stars' => '5', 'featured' => false],
            ['id' => 'vt2', 'name' => '[Client name]', 'role' => '[Role, company]', 'video' => '', 'format' => 'vertical', 'cover' => 'assets/img/work/p6.jpg', 'quote' => '[One line the client says in the video.]', 'project' => '[What you made for them]', 'duration' => '', 'stars' => '5', 'featured' => false],
            ['id' => 'vt3', 'name' => '[Client name]', 'role' => '[Role, company]', 'video' => '', 'format' => 'landscape', 'cover' => 'assets/img/work/p8.jpg', 'quote' => '[One line the client says in the video.]', 'project' => '[What you made for them]', 'duration' => '', 'stars' => '5', 'featured' => false],
        ],
        'process' => [
            ['id' => 'pr1', 'label' => '#01 · Day 1', 'title' => 'Brief & strategy', 'text' => 'We agree on goals, audience, references and the deadline.'],
            ['id' => 'pr2', 'label' => '#02 · Rough cut', 'title' => 'Story & pacing', 'text' => 'Structure, music and rhythm, shared early for your feedback.'],
            ['id' => 'pr3', 'label' => '#03 · Polish', 'title' => 'Motion, grade & sound', 'text' => 'Graphics, colour, mix and your revision rounds.'],
            ['id' => 'pr4', 'label' => '#04 · Delivery', 'title' => 'Masters for every platform', 'text' => '16:9, 9:16 and 1:1 exports with captions, ready to publish.'],
        ],
        'contact' => [
            'email' => 'hello@yourdomain.com',
            'whatsapp' => '',
            'whatsappMessage' => 'Hi Jesun, I have a video project I’d like to talk about.',
            'bookingUrl' => '',
            'location' => 'Worldwide · Remote',
            'responseTime' => 'Replies within 24 hours',
            'socials' => [
                ['id' => 'so1', 'label' => 'YouTube', 'url' => ''],
                ['id' => 'so2', 'label' => 'Instagram', 'url' => ''],
                ['id' => 'so3', 'label' => 'Behance', 'url' => ''],
                ['id' => 'so4', 'label' => 'LinkedIn', 'url' => ''],
            ],
        ],
        'settings' => [
            'siteName' => 'MD Jesun',
            'role' => 'Video editor & motion designer',
            'footerText' => 'Video editor and motion designer, working with clients worldwide.',
            'seoTitle' => 'MD Jesun — Video Editor & Motion Designer',
            'seoDescription' => 'MD Jesun is a video editor and motion graphics designer cutting commercials, YouTube content and brand films for clients worldwide.',
            'ogImage' => '',
            'logo' => '',
            'favicon' => '',
            'intro' => true,
            'cursor' => true,
            'autoplay' => true,
            'transitions' => true,
            'timeline' => true,
            'theme' => 'midnight',
        ],
    ];
}
