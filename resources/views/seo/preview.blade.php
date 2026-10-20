<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | {{ $siteName }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index, follow, max-image-preview:large">

    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="{{ $type }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="{{ $title }} | {{ $siteName }}">
    <meta property="og:description" content="{{ $description }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        @if (str_starts_with($image, 'https://'))
            <meta property="og:image:secure_url" content="{{ $image }}">
        @endif
        <meta property="og:image:alt" content="{{ $title }}">
    @endif
    @if (!empty($publishedTime))
        <meta property="article:published_time" content="{{ $publishedTime }}">
    @endif
    @if (!empty($modifiedTime))
        <meta property="article:modified_time" content="{{ $modifiedTime }}">
    @endif

    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }} | {{ $siteName }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if ($image)
        <meta name="twitter:image" content="{{ $image }}">
        <meta name="twitter:image:alt" content="{{ $title }}">
    @endif
    <meta name="twitter:site" content="@Septentrional">

    <script type="application/ld+json">{!! $structuredDataJson !!}</script>
</head>
<body>
    <main>
        <article>
            <h1>{{ $title }}</h1>
            @if ($image)
                <img src="{{ $image }}" alt="{{ $title }}">
            @endif
            <p>{{ $description }}</p>
            @if (!empty($publishedTime))
                <time datetime="{{ $publishedTime }}">{{ $publishedTime }}</time>
            @endif
            @if (!empty($content))
                <div>{{ $content }}</div>
            @endif
            <p><a href="{{ $canonicalUrl }}">Lire ou écouter sur Septentrional</a></p>
        </article>
    </main>
</body>
</html>
