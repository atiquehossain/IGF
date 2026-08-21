// /* eslint-disable no-undef */
// describe('Video Content', () => {
// // cy.refreshDatabase();
//   before(() => {
//     // cy.seed();
//   });

//   beforeEach(() => {
//     cy.loginWithUsername();
//     cy.visit('/admin/video-content');
//   });

//   it('create new video-content by save', () => {
//     cy.visit('/admin/video-content/create');
//     const path = 'cypress/fixtures/banner.jpg';
//     const audio = 'cypress/fixtures/video.mp4';
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=video-content-title-${lang}]`).type(`video-content update ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=video-content-image-${lang}]`).selectFile(path, { force: true });
//         cy.wait(500);
//         cy.get(`[data-e2e=video-content-${lang}]`).selectFile(audio, { force: true });
//         cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//         cy.get(`[data-e2e=video-content-order-by-${lang}]`).type(5);
//       });
//     } else {
//       const lang = 'en';
//       cy.get(`[data-e2e=video-content-title-${lang}]`).type(`video-content update ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=video-content-image-${lang}]`).selectFile(path, { force: true });
//       cy.wait(500);
//       cy.get(`[data-e2e=video-content-${lang}]`).selectFile(audio, { force: true });
//       cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//       cy.get(`[data-e2e=video-content-order-by-${lang}]`).type(5);
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('create new video-content by save and update', () => {
//     cy.visit('/admin/video-content/create');
//     const path = 'cypress/fixtures/banner.jpg';
//     const audio = 'cypress/fixtures/video.mp4';

//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=video-content-title-${lang}]`).type(`video-content update ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=video-content-image-${lang}]`).selectFile(path, { force: true });
//         cy.wait(500);
//         cy.get(`[data-e2e=video-content-${lang}]`).selectFile(audio, { force: true });
//         cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//         cy.get(`[data-e2e=video-content-order-by-${lang}]`).type(5);
//       });

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=video-content-title-${lang}]`).type(`video-content update ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=video-content-image-${lang}]`).selectFile(path, { force: true });
//         cy.wait(500);
//         cy.get(`[data-e2e=video-content-${lang}]`).selectFile(audio, { force: true });
//         cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//         cy.get(`[data-e2e=video-content-order-by-${lang}]`).type(5);
//       });
//     } else {
//       const lang = 'en';

//       cy.get(`[data-e2e=video-content-title-${lang}]`).type(`video-content update ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=video-content-image-${lang}]`).selectFile(path, { force: true });
//       cy.wait(500);
//       cy.get(`[data-e2e=video-content-${lang}]`).selectFile(audio, { force: true });
//       cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//       cy.get(`[data-e2e=video-content-order-by-${lang}]`).type(5);

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get(`[data-e2e=video-content-title-${lang}]`).type(`video-content update ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=video-content-image-${lang}]`).selectFile(path, { force: true });
//       cy.wait(500);
//       cy.get(`[data-e2e=video-content-${lang}]`).selectFile(audio, { force: true });
//       cy.get(`[data-e2e=duration_time-${lang}]`).type(12);
//       cy.get(`[data-e2e=video-content-order-by-${lang}]`).type(5);
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('update video-content by save', () => {
//     cy.get('#video_table tbody tr a.edit').first().click();
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
