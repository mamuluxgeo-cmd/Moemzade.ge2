# Moemzade.ge

საქართველოს მასწავლებლებისა და მენტორების საძიებო პლატფორმა.

## ტექნოლოგია

- PHP 8.3
- MySQL
- HTML, CSS და vanilla JavaScript
- DirectAdmin shared hosting

## უსაფრთხო კონფიგურაცია

რეალური გარემოს მონაცემები ინახება მხოლოდ სერვერის `.env` ფაილში. ატვირთული
მედია, ლოგები და დროებითი ფაილები Git-ში არ ინახება.

## შემოწმება

Pull request-ებზე GitHub Actions ამოწმებს PHP სინტაქსსა და feature smoke
ტესტებს. Production deployment თავდაპირველად მხოლოდ ხელით იშვება და იყენებს
GitHub Actions-ის დაშიფრულ FTP Secrets-ს.
