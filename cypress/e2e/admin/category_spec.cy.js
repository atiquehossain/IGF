// /* eslint-disable no-undef */
// describe('Category', () => {
//   // cy.refreshDatabase();
//   before(() => {
//     // cy.seed();
//   });

//   beforeEach(() => {
//     cy.loginWithUsername();
//     cy.visit('/admin/category');
//   });

//   it('create new category by save', () => {
//     cy.visit('/admin/category/create');
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=category-name-${lang}]`).type(`category ${lang}`);
//         cy.get(`[data-e2e=category-description-${lang}]`).type(`category description ${lang}`);
//       });
//     } else {
//       const lang = 'en';
//       cy.get(`[data-e2e=category-name-${lang}]`).type(`category ${lang}`);
//       cy.get(`[data-e2e=category-description-${lang}]`).type(`category description ${lang}`);
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('create new category by save and update', () => {
//     cy.visit('/admin/category/create');
//     if (Cypress.env('APP_LOCALIZATION')) {
//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=category-name-${lang}]`).type(`category update ${lang}`);
//         cy.get(`[data-e2e=category-description-${lang}]`).type(`category update description ${lang}`);
//       });

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get('#pills-tab li').each((element) => {
//         const lang = element.attr('data-id');
//         cy.get(`#${lang}-tab`).click();
//         cy.get(`[data-e2e=category-name-${lang}]`).type(`category update2 ${lang}`);
//         cy.get(`[data-e2e=category-description-${lang}]`).type(`category update2 description ${lang}`);
//       });
//     } else {
//       const lang = 'en';

//       cy.get(`[data-e2e=category-name-${lang}]`).type(`category update ${lang}`);
//       cy.get(`[data-e2e=category-description-${lang}]`).type(`category update description ${lang}`);

//       cy.wait(1000);
//       cy.get('button[name="save_and_update"]').click();
//       cy.wait(1000);

//       cy.get(`[data-e2e=category-name-${lang}]`).type(`category update2 ${lang}`);
//       cy.get(`[data-e2e=category-description-${lang}]`).type(`category update2 description ${lang}`);
//     }

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('update category by save', () => {
//     cy.get('#category_table tbody tr a.edit').first().click();
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
