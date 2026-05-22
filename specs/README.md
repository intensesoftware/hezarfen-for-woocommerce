# Hezarfen Specs

AI ve insan tarafından okunabilir özellik spesifikasyonları. Kod değil davranışı tarif eder; bir özelliğin **ne yaptığını**, **hangi senaryolarda ne beklendiğini**, **hangi dosya ve hook'lara dokunduğunu** anlatır.

Bu klasör `.distignore` üzerinden dağıtım paketinin dışında tutulur — sadece geliştirme repo'sunda yaşar.

---

## Dizin Yapısı

```
specs/
├── README.md                       # bu dosya
├── modules/                        # her modül için bir spec dosyası
│   ├── shipment-tracking.md
│   ├── sales-contract.md
│   ├── neighborhood-selection.md
│   ├── invoice-fields.md
│   ├── sms-automation.md
│   ├── checkout-customization.md
│   └── my-account.md
└── shared/                         # tüm modülleri kesen referanslar
    ├── glossary.md                 # MSS, ÖBF, desi, IYS, KVKK vs.
    ├── data-model.md               # option key'leri, meta key'leri, tablo şemaları
    └── hooks.md                    # public action/filter hook envanteri
```

---

## Dosya Formatı (Spec Format)

Her modül dosyası **YAML frontmatter + Markdown** yapısındadır. Davranışlar **Gherkin (Given/When/Then)** ile yazılır.

### Frontmatter Alanları

```yaml
---
id: kebab-case-slug              # benzersiz tanımlayıcı, related: alanlarında bu kullanılır
title: İnsan okur başlık
status: stable | beta | deprecated
since: 1.0.0                     # özelliğin eklendiği plugin sürümü
owner: hezarfen-core             # bakım sorumlusu
entry_files:                     # bu modülün ana giriş dosyaları (göreceli yol)
  - includes/foo.php
  - packages/bar/bar.php
depends_on: [woocommerce]        # zorunlu bağımlılıklar
optional_deps: [hezarfen-pro]    # opsiyonel entegrasyonlar
related: [other-module-id]       # bu spec'in linklediği diğer modüller
---
```

### Bölüm Şablonu

```markdown
## Amaç
Bir paragraf — özelliğin neden var olduğu.

## Kapsam
- Madde 1
- Madde 2 (kod referansıyla: `path/to/file.php:42`)

## Veri Modeli
- option `hezarfen_xxx` — açıklama
- order meta `_hezarfen_yyy` — açıklama, format

## Davranışlar

### Senaryo: Kısa senaryo adı
- **Given** ön koşul
- **When** kullanıcı/sistem aksiyonu
- **Then** beklenen sonuç
- **And** ek sonuç (varsa)

## Edge Cases
- Köşe durumu 1
- Köşe durumu 2

## UI Lokasyonları
- Admin: …
- Frontend: …

## Hooks
- action: `hezarfen_foo` — ne zaman, hangi parametrelerle
- filter: `hezarfen_bar` — ne zaman, dönüş tipi

## Sınama Notları
- Manuel test adımı
- HPOS açık/kapalı matrisi vb.
```

---

## Yazım Kuralları

1. **Davranış > kod**. Spec, "kod ne yapıyor"u değil "özellik nasıl davranmalı"yı anlatır. Yenileme yapılırken davranış sözleşmesi olarak iş görür.
2. **Senaryolar atomik olsun**. Bir senaryo tek bir akışı kapsar. Çoklu sonuç gerekirse ayrı senaryo yaz.
3. **Mutlak path verme**. `wp-content/plugins/hezarfen-for-woocommerce/` köküne göre göreceli yol kullan (`includes/Checkout.php:124`).
4. **Türkçe/İngilizce karışıklığı normal**. Plugin'in kendisi Türkçe odaklı; teknik terimler İngilizce kalabilir.
5. **Bilinen sınırı yaz**. "Şu an desteklenmiyor" notu eksik özelliği değil, **kasıtlı tasarım kararını** belgeler.
6. **Linkleme**: ilgili modüllere `[id'sini referans vererek][1]` yaz, dosya sonunda `[1]: ./other.md` ile çöz; ya da düz `./other-module.md` linki kullan.

---

## Yeni Bir Modül Eklerken

1. `modules/<id>.md` dosyasını yukarıdaki şablonla aç.
2. Frontmatter'da `id` benzersiz olsun.
3. `shared/data-model.md` içine yeni option/meta key'leri ekle.
4. Yeni public hook varsa `shared/hooks.md`'ye işle.
5. Bu README'deki "Dizin Yapısı" listesine ekle.

---

## Status Değerleri

- **stable** — Production'da, breaking change yapılmaz.
- **beta** — Yayında ama davranış değişebilir, opt-in.
- **deprecated** — Çıkartılacak, yerine ne kullanılacağı `replaced_by:` alanında belirtilir.
