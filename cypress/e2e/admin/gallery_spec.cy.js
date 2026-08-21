// /* eslint-disable no-undef */
// describe('Gallery', () => {
//   // cy.refreshDatabase();
//   before(() => {
//     // cy.seed();
//   });

//   beforeEach(() => {
//     cy.loginWithUsername();
//     cy.visit('/admin/gallery');
//   });

//   it('create new gallery by save', () => {
//     cy.visit('/admin/gallery/create');
//     const path = 'cypress/fixtures/banner.jpg';
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li.main').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=gallery-album-id-${lang}]`).select(1);
//         cy.get(`[data-e2e=gallery-name-${lang}]`).type(`gallery ${lang}`);
//         cy.get(`[data-e2e=gallery-description-${lang}]`).type(`gallery description ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=gallery-image-${lang}]`).selectFile(path, { force: true });
//       });
//     } else {
//       const lang = 'en';
//       cy.get(`[data-e2e=gallery-album-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=gallery-name-${lang}]`).type(`gallery ${lang}`);
//       cy.get(`[data-e2e=gallery-description-${lang}]`).type(`gallery description ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=gallery-image-${lang}]`).selectFile(path, { force: true });
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('create new gallery by save and update', () => {
//     cy.visit('/admin/gallery/create');
//     const path = 'cypress/fixtures/banner.jpg';
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li.main').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=gallery-album-id-${lang}]`).select(1);
//         cy.get(`[data-e2e=gallery-name-${lang}]`).type(`gallery update ${lang}`);
//         cy.get(`[data-e2e=gallery-description-${lang}]`).type(`gallery update description ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=gallery-image-${lang}]`).selectFile(path, { force: true });
//       });

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get('#pills-tab li.main').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=gallery-album-id-${lang}]`).select(1);
//         cy.get(`[data-e2e=gallery-name-${lang}]`).type(`gallery update 2 ${lang}`);
//         cy.get(`[data-e2e=gallery-description-${lang}]`).type(`gallery update description 2 ${lang}`);
//         cy.wait(500);
//         cy.get(`[data-e2e=gallery-image-${lang}]`).selectFile(path, { force: true });
//       });
//     } else {
//       const lang = 'en';

//       cy.get(`[data-e2e=gallery-album-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=gallery-name-${lang}]`).type(`gallery update 2 ${lang}`);
//       cy.get(`[data-e2e=gallery-description-${lang}]`).type(`gallery update description 2 ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=gallery-image-${lang}]`).selectFile(path, { force: true });

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get(`[data-e2e=gallery-album-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=gallery-name-${lang}]`).type(`gallery update 2 ${lang}`);
//       cy.get(`[data-e2e=gallery-description-${lang}]`).type(`gallery update description 2 ${lang}`);
//       cy.wait(500);
//       cy.get(`[data-e2e=gallery-image-${lang}]`).selectFile(path, { force: true });
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('update gallery by save', () => {
//     cy.get('#gallery_table tbody tr a.edit').first().click();
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li.main').each((element) => {
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
