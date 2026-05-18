{
  "id": "{{ url('/') }}",
  "name": "GDA BuildOps — Gestion de chantier",
  "short_name": "GDA BuildOps",
  "description": "Suivi de chantier, photos, rapports et tableau de bord GDA",
  "start_url": "{{ url('/') }}",
  "scope": "{{ url('/') }}",
  "display": "standalone",
  "orientation": "any",
  "background_color": "#1a1814",
  "theme_color": "#c8521a",
  "lang": "fr",
  "dir": "ltr",
  "categories": ["business", "productivity"],
  "icons": [
    {
      "src": "{{ asset('img/Constfondblanc.jpg') }}",
      "sizes": "192x192",
      "type": "image/jpeg",
      "purpose": "any"
    },
    {
      "src": "{{ asset('img/Constfondblanc.jpg') }}",
      "sizes": "512x512",
      "type": "image/jpeg",
      "purpose": "any"
    },
    {
      "src": "{{ asset('img/Constfondblanc.jpg') }}",
      "sizes": "512x512",
      "type": "image/jpeg",
      "purpose": "maskable"
    }
  ]
}
