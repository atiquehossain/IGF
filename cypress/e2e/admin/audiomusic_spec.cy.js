// /* eslint-disable no-undef */
// describe('Audio-music', () => {
//   // cy.refreshDatabase();
//   before(() => {
//     // cy.seed();
//   });

//   beforeEach(() => {
//     cy.loginWithUsername();
//     cy.visit('/admin/audio-music');
//   });

//   it('create new audio-music by save', () => {
//     cy.visit('/admin/audio-music/create');
//     const path = 'cypress/fixtures/banner.jpg';
//     const audio = 'cypress/fixtures/audio.mp3';
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=audio-music-title-${lang}]`).type(`audio-music update ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=audio-music-image-${lang}]`).selectFile(path, { force: true });
//         cy.wait(500);
//         cy.get(`[data-e2e=audio-music-audio-${lang}]`).selectFile(audio, { force: true });
//         cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//         cy.get(`[data-e2e=audio-music-order-by-${lang}]`).type(5);
//       });
//     } else {
//       const lang = 'en';
//       cy.get(`[data-e2e=audio-music-title-${lang}]`).type(`audio-music update ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=audio-music-image-${lang}]`).selectFile(path, { force: true });
//       cy.wait(500);
//       cy.get(`[data-e2e=audio-music-audio-${lang}]`).selectFile(audio, { force: true });
//       cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//       cy.get(`[data-e2e=audio-music-order-by-${lang}]`).type(5);
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('create new audio-music by save and update', () => {
//     cy.visit('/admin/audio-music/create');
//     const path = 'cypress/fixtures/banner.jpg';
//     const audio = 'cypress/fixtures/audio.mp3';

//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=audio-music-title-${lang}]`).type(`audio-music update ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=audio-music-image-${lang}]`).selectFile(path, { force: true });
//         cy.wait(500);
//         cy.get(`[data-e2e=audio-music-audio-${lang}]`).selectFile(audio, { force: true });
//         cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//         cy.get(`[data-e2e=audio-music-order-by-${lang}]`).type(5);
//       });

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=audio-music-title-${lang}]`).type(`audio-music update ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=audio-music-image-${lang}]`).selectFile(path, { force: true });
//         cy.wait(500);
//         cy.get(`[data-e2e=audio-music-audio-${lang}]`).selectFile(audio, { force: true });
//         cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//         cy.get(`[data-e2e=audio-music-order-by-${lang}]`).type(5);
//       });
//     } else {
//       const lang = 'en';

//       cy.get(`[data-e2e=audio-music-title-${lang}]`).type(`audio-music update ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=audio-music-image-${lang}]`).selectFile(path, { force: true });
//       cy.wait(500);
//       cy.get(`[data-e2e=audio-music-audio-${lang}]`).selectFile(audio, { force: true });
//       cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//       cy.get(`[data-e2e=audio-music-order-by-${lang}]`).type(5);

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get(`[data-e2e=audio-music-title-${lang}]`).type(`audio-music update ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=audio-music-image-${lang}]`).selectFile(path, { force: true });
//       cy.wait(500);
//       cy.get(`[data-e2e=audio-music-audio-${lang}]`).selectFile(audio, { force: true });
//       cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//       cy.get(`[data-e2e=audio-music-order-by-${lang}]`).type(5);
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('update audio-music by save', () => {
//     cy.get('#audio_table tbody tr a.edit').first().click();
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.wait(500);
//         cy.get(`#${lang}-tab`).click();
//       });
//     }

//     cy.wait(1000);
//     cy.get('button[name="save_and_update"]').click();
//     cy.wait(1000);
//     cy.get('#go-back').click();
//   });
// });
