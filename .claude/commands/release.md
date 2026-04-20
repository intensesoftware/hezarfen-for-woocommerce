---
description: Hezarfen WooCommerce eklentisi için release PR'ı hazırla (Türkçe changelog, otomatik versiyon bump, GitHub PR)
argument-hint: "[major]"
allowed-tools: Bash, Read, Edit, Write, Grep, Glob, TodoWrite
---

# Hezarfen Release PR

Argüman: `$ARGUMENTS` — boşsa otomatik minor/patch bump; `major` geçilirse major bump.

Bu komut **her zaman** plugin kök dizininde çalışır:
`/Users/mskapusuz/Local Sites/hezarfen-dev/app/public/wp-content/plugins/hezarfen-for-woocommerce`

## Adımlar

### 1. Ortam doğrulaması
- `cd` ile plugin dizinine git, `git status` ile temiz mi kontrol et. Kirli ise dur ve kullanıcıya bildir.
- Mevcut branch `master` olmalı; değilse uyar ve durdur.
- `git fetch --tags origin` çalıştır (en güncel tag'ler).

### 2. Son tag'i bul ve commit'leri topla
- En son semver tag: `git tag --sort=-v:refname | head -1`
- O tag'den `HEAD`'e commit'leri al: `git log <tag>..HEAD --pretty=format:'%h %s' --no-merges`
- Eğer hiç yeni commit yoksa: kullanıcıya "release atılacak değişiklik yok" deyip dur.

### 3. Versiyon bump kararı
- `$ARGUMENTS` `major` içeriyorsa → major bump (X+1.0.0).
- Değilse, commit subject'lerini incele:
  - En az bir commit `feat:` / `feat(...):` ile başlıyorsa → minor (X.Y+1.0)
  - Aksi halde → patch (X.Y.Z+1)
- **Major'u asla otomatik seçme** — sadece argüman ile.

### 4. Türkçe changelog taslağı
- Commit'leri kategorize et (feat → "Yeni özellikler", fix → "Düzeltmeler", diğer → "Diğer iyileştirmeler"). Saf chore/release commit'lerini ("new tags", "readme.txt update", "X.Y.Z" gibi) ele.
- Kullanıcı odaklı, kısa ve sade Türkçe cümleler kur. Conventional commit prefix'lerini (`feat:`, `fix:`) çıkar. Mümkünse mevcut readme.txt changelog stilini taklit et (`* Cümle.` formatı).
- Bu draft'ı **kullanıcıya göster** ve şunu sor:
  > "Önerilen versiyon: **X.Y.Z** (bump türü: <minor|patch|major>). Aşağıdaki Türkçe changelog'u onaylıyor musun, yoksa düzenlemek ister misin?"
- **DUR ve kullanıcının onayını/düzenlemelerini bekle.** Kullanıcı onaylamadan veya düzeltme istemeden dosyalara dokunma.

### 5. Dosya güncellemeleri (kullanıcı onayından sonra)
Şu üç dosyayı güncelle:

**`hezarfen-for-woocommerce.php`:**
- Plugin header'daki ` * Version: X.Y.Z` satırı
- `define( 'WC_HEZARFEN_VERSION', 'X.Y.Z' );` satırı

**`readme.txt`:**
- `Stable tag: X.Y.Z` satırı
- `== Changelog ==` başlığının hemen altına yeni blok ekle:
  ```
  = X.Y.Z - YYYY-MM-DD =
  * Onaylanmış changelog satırları...

  ```
  Tarih için `date +%Y-%m-%d` kullan (bugünün tarihi).

Edit tool ile uygula, eski sürüm bloklarını silme.

### 6. Build adımları (commit'ten önce)
Versiyon güncellemelerinden sonra, commit'ten **önce** şunları sırayla çalıştır:

1. `composer install --no-dev -o` — production dependency'lerini optimize edilmiş autoloader ile kur (sanity check; `vendor/` zaten gitignored).
2. `npm run build` — `assets/admin/order-edit/build/` ve `assets/admin/flowbite/build/` altındaki built asset'leri yeniden üret. Bu dosyalar git'te **tracked**, dolayısıyla commit'lenmesi gerekiyor.

Herhangi biri hata verirse dur, kullanıcıya bildir, devam etme.

### 7. Branch + commit + push + PR
- Branch oluştur: `git checkout -b release/vX.Y.Z`
- Stage: `git add hezarfen-for-woocommerce.php readme.txt assets/admin/order-edit/build assets/admin/flowbite/build`
- `git status` ile beklenmeyen değişiklik var mı kontrol et; varsa kullanıcıya sor.
- Commit:
  ```
  Release vX.Y.Z

  Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
  ```
- Push: `git push -u origin release/vX.Y.Z`
- PR aç (`gh pr create`):
  - **Title:** `Release vX.Y.Z` (bu format kritik — auto-tag workflow buna bakar)
  - **Base:** `master`
  - **Body:**
    ```
    ## Özet
    Versiyon X.Y.Z release PR'ı.

    ## Changelog
    <onaylanmış Türkçe changelog satırları>

    ## Bump türü
    <minor|patch|major>

    ---
    Merge edildiğinde `auto-tag-release.yml` workflow'u tag ve GitHub Release'i otomatik oluşturur, ardından `deploy.yml` WordPress.org'a deploy eder.

    🤖 Generated with [Claude Code](https://claude.com/claude-code)
    ```

### 8. Sonuç
Kullanıcıya PR URL'ini ver ve şunu hatırlat: "Merge edilince tag ve GitHub Release otomatik oluşur, WP.org deploy'u tetiklenir."

## Önemli kurallar
- Kullanıcı onayı **olmadan** dosya değişikliği veya commit YAPMA.
- `--no-verify`, `--force`, `reset --hard` gibi destructive flag'lere asla başvurma.
- Commit subject'ini `Release vX.Y.Z` formatından sapma — auto-tag workflow buna bakmasa da PR title'ı buna bakıyor; tutarlı kal.
- Eğer `gh pr create` hata verirse (örn. branch zaten varsa) durdur ve kullanıcıya sor.
