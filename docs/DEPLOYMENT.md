# ProService deployment

Production deployment ავტომატურად გაეშვება მხოლოდ `main` branch-ზე დამტკიცებული ცვლილებებისთვის. პაროლები და გასაღებები GitHub-ის ფაილებში არ ჩაიწერება.

## ჰოსტინგისგან საჭირო ინფორმაცია

- deployment პროტოკოლი: სასურველია SFTP/SSH; თუ არ აქვთ, FTPS;
- სერვერის hostname და port;
- deployment მომხმარებლის username;
- საიტის web root, მაგალითად `public_html`;
- აქვს თუ არა Git repository deployment ფუნქცია მართვის პანელში;
- MySQL host, database name და username;
- PHP 8.3-ზე `pdo_mysql`, `mbstring`, `curl` და Imagick ან GD WebP მხარდაჭერა;
- cron job-ის შესაძლებლობა backup-ისა და restore-test-ისთვის.

## GitHub-ში შესანახი secrets

როდესაც პროტოკოლი დაზუსტდება, repository settings-ში შეიქმნება მხოლოდ შესაბამისი secrets, მაგალითად `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PASSWORD`/`DEPLOY_KEY` და `DEPLOY_PATH`. რეალური მნიშვნელობები არც commit-ში და არც pull request-ში არ გამოჩნდება.

## სერვერზე ერთხელ გასაკეთებელი

1. `.env.example`-ის მიხედვით შეიქმნას `.env` უშუალოდ სერვერზე.
2. ჩაიწეროს production `APP_KEY`, MySQL მონაცემები და ადმინისტრატორის password hash.
3. გაეშვას `database/schema.sql` phpMyAdmin-იდან ან `php bin/migrate.php` SSH-დან.
4. `media` და `storage/tmp` საქაღალდეებს მიეცეს PHP-ისთვის ჩაწერის უფლება.
5. შემოწმდეს HTTPS, routing, ფოტოს ატვირთვა და backup-ის აღდგენა.

Deployment workflow შეგნებულად არ არის აქტიური, სანამ ზუსტი პროტოკოლი და სერვერის გზა არ ვიცით — არასწორმა ავტომატიზაციამ შეიძლება მოქმედი საიტის ფაილები არასწორ ადგილას გადაწეროს.

