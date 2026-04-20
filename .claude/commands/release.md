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
- `git fetch --tags --prune origin` çalıştır (master, develop, tag'ler güncel olsun).
- Mevcut branch `develop` olmalı; değilse `git checkout develop` ile geç (veya kullanıcıya sor).
- `git pull --ff-only origin develop` ile lokal develop'ı güncelle. Fast-forward değilse dur.
- **Develop master'la sync mi kontrol et.** `git log develop..origin/master --oneline` çıktı veriyorsa develop master'ın gerisinde (önceki release PR'ı merge commit ile geldi). Önce `git merge --ff-only origin/master` ile FF et, başarılı olursa `git push origin develop`. FF mümkün değilse dur ve kullanıcıya sor.

### 2. Son tag'i bul ve yayınlanmamış commit'leri topla
- En son semver tag: `LATEST_TAG=$(git tag --sort=-v:refname | head -1)`
- Bu tag'den `develop`'a kadarki yayınlanmamış commit'ler: `git log "$LATEST_TAG"..develop --pretty=format:'%h %s' --no-merges`
  - Bu liste hem master'a düşmüş ama henüz tag'lenmemiş commit'leri (önceki release'den sonra merge edilen tooling/hotfix gibi şeyler) hem de develop'ta master'a girmemiş commit'leri kapsar — develop az önce master'la FF'lendiği için ikisi de aynı bakış açısından görünür.
- Eğer hiç commit yoksa: "release atılacak değişiklik yok" deyip dur.

### 3. Versiyon bump kararı
- `$ARGUMENTS` `major` içeriyorsa → major bump (X+1.0.0).
- Değilse, commit subject'lerini incele:
  - En az bir commit `feat:` / `feat(...):` ile başlıyorsa → minor (X.Y+1.0)
  - Aksi halde → patch (X.Y.Z+1)
- **Major'u asla otomatik seçme** — sadece argüman ile.

### 4. Türkçe changelog taslağı

**Commit'leri 1:1 çevirme.** Eklenti son kullanıcısı (mağaza sahibi) için yazıyorsun; geliştiriciye değil.

#### Yazım stili — readme.txt mevcut tarzına birebir uy:
- **Geçmiş edilgen** çatı kullan: "eklendi", "düzeltildi", "iyileştirildi", "güncellendi", "kaldırıldı".
  - ❌ "Sipariş ekranında fatura bilgilerini göster." (emir/şimdiki)
  - ✅ "Sipariş görüntüleme ekranında fatura bilgileri eklendi."
- Conventional commit prefix'lerini (`feat:`, `fix:`, `chore:`) **at**.
- Tek cümle, sonu nokta, başında `* `.
- Teknik jargon yok (`<br/>`, "billing address section", "merge", commit hash vs.). Kullanıcı arayüzü dilinde konuş.

#### Konsolidasyon — agresif birleştir:
- Aynı özelliği farklı yüzeylere ekleyen birden fazla commit varsa, **tek satırda** topla. Örn:
  - 4 ayrı commit: "show invoice info in emails", "show on thank-you page", "show on my-account view", "show invoice type on order view"
  - ❌ 4 ayrı bullet
  - ✅ Tek bullet: "Fatura bilgileri sipariş e-postalarında, teşekkür sayfasında ve hesabım sipariş detayında gösterilmeye başlandı."
- Kullanıcının fark edemeyeceği iç düzeltmeleri (CSS spacing tweak'leri, küçük HTML temizlikleri, refactor, lint düzeltmeleri) **silebilirsin** — en fazla genel "iyileştirildi" cümlesine soğutur. Ürün davranışını gözle görülür şekilde değiştiren her commit'i koru.
- **Aynı release'te yeni eklenen bir özelliğe ait geliştirme-sırası commit'lerini ayrı "iyileştirildi" satırı yapma.** Özellik henüz yayınlanmıyor; yapılan tweak'ler özelliğin ilk halinin parçası — feature bullet'ına dahildir, ayrıca sayılmaz.
  - ❌ "Fatura bilgileri ... gösterilmeye başlandı." + "Fatura bilgileri alanında görsel iyileştirmeler yapıldı."
  - ✅ Sadece tek satır: "Fatura bilgileri ... gösterilmeye başlandı."
  - "İyileştirildi/giderildi" satırları **yalnızca önceki release'lerde zaten yayınlanmış** davranışı değiştiren commit'ler için.
- "new tags", "readme.txt update", "X.Y.Z", "bump version" gibi release house-keeping commit'lerini her zaman at.

#### Sıralama:
Önce yeni özellikler (eklendi), sonra iyileştirmeler (güncellendi/iyileştirildi), en sonda hata düzeltmeleri (düzeltildi/giderildi). Kategori başlığı yazma, sadece `* ` listesi.

#### Sunum:
Draft'ı kullanıcıya göster ve şunu sor:
> "Önerilen versiyon: **X.Y.Z** (bump türü: <minor|patch|major>). Aşağıdaki Türkçe changelog'u onaylıyor musun, yoksa düzenlemek ister misin?"

**DUR ve kullanıcının onayını/düzenlemelerini bekle.** Kullanıcı onaylamadan veya düzeltme istemeden dosyalara dokunma.

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

### 7. Commit + push + PR
Versiyon bump'ı **doğrudan `develop` branch'ine** commit'lenir; ayrı release branch'i açılmaz.

- Hâlâ `develop` üzerinde olduğunu doğrula (`git branch --show-current`).
- Stage: `git add hezarfen-for-woocommerce.php readme.txt assets/admin/order-edit/build assets/admin/flowbite/build`
- `git status` ile beklenmeyen değişiklik var mı kontrol et; varsa kullanıcıya sor.
- Commit:
  ```
  Release vX.Y.Z

  Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
  ```
- Push: `git push origin develop`
- PR aç (`gh pr create`):
  - **Title:** `Release vX.Y.Z` (bu format kritik — auto-tag workflow buna bakar)
  - **Base:** `master`
  - **Head:** `develop`
  - Aynı `develop → master` PR'ı zaten açıksa `gh pr create` hata verir — bu durumda dur ve kullanıcıya sor.
  - **Body:**
    ```
    ## Özet
    Versiyon X.Y.Z release PR'ı (develop → master).

    ## Changelog
    <onaylanmış Türkçe changelog satırları>

    ## Bump türü
    <minor|patch|major>

    ---
    Merge edildiğinde `auto-tag-release.yml` workflow'u tag, GitHub Release ve WordPress.org deploy'unu otomatik tek seferde yapar.

    🤖 Generated with [Claude Code](https://claude.com/claude-code)
    ```

### 8. Sonuç
Kullanıcıya PR URL'ini ver ve şunu hatırlat: "Merge edilince tag ve GitHub Release otomatik oluşur, WP.org deploy'u tetiklenir."

## Önemli kurallar
- Kullanıcı onayı **olmadan** dosya değişikliği veya commit YAPMA.
- `--no-verify`, `--force`, `reset --hard` gibi destructive flag'lere asla başvurma.
- Commit subject'ini `Release vX.Y.Z` formatından sapma — auto-tag workflow buna bakmasa da PR title'ı buna bakıyor; tutarlı kal.
- Eğer `gh pr create` hata verirse (örn. branch zaten varsa) durdur ve kullanıcıya sor.
