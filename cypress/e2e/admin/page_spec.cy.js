// /* eslint-disable no-undef */
// describe('Page', () => {
//   // cy.refreshDatabase();
//   before(() => {
//     // cy.seed();
//   });

//   beforeEach(() => {
//     cy.loginWithUsername();
//     cy.visit('/admin/page');
//   });

//   it('create new page by save', () => {
//     cy.visit('/admin/page/create');
//     cy.get('#pills-tab li').each((element) => {
//       const lang = element.attr('data-id');
//       cy.get(`#${lang}-tab`).click();
//       cy.get(`[data-e2e=page-name-${lang}]`).type(`title ${lang}`);
//       cy.get(`[data-e2e=page-sub-title-${lang}]`).type(`sub title  ${lang}`);
//       cy.get(`[data-e2e=page-category-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=page-banner-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=page-description-${lang}]`).type(`description ${lang}`, { force: true });
//       cy.get(`[data-e2e=page-inline-css-${lang}]`).type('h1 { fon-weight: bold }', { parseSpecialCharSequences: false });
//       cy.get(`[data-e2e=page-order-by-${lang}]`).type(5);
//     });

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('create new page by save and update', () => {
//     cy.visit('/admin/page/create');
//     cy.get('#pills-tab li').each((element) => {
//       const lang = element.attr('data-id');
//       cy.get(`#${lang}-tab`).click();
//       cy.get(`[data-e2e=page-name-${lang}]`).type(`title ${lang}`);
//       cy.get(`[data-e2e=page-sub-title-${lang}]`).type(`sub title  ${lang}`);
//       cy.get(`[data-e2e=page-category-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=page-banner-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=page-description-${lang}]`).type(`description ${lang}`, { force: true });
//       cy.get(`[data-e2e=page-inline-css-${lang}]`).type(`css ${lang}`);
//       cy.get(`[data-e2e=page-order-by-${lang}]`).type(5);
//     });

//     cy.wait(1000);
//     cy.get('button[name="save_and_update"]').click();
//     cy.wait(1000);

//     cy.get('#pills-tab li').each((element) => {
//       const lang = element.attr('data-id');
//       cy.get(`#${lang}-tab`).click();
//       cy.get(`[data-e2e=page-name-${lang}]`).type(`title ${lang}`);
//       cy.get(`[data-e2e=page-sub-title-${lang}]`).type(`sub title  ${lang}`);
//       cy.get(`[data-e2e=page-category-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=page-banner-id-${lang}]`).select(1);
//       cy.get(`[data-e2e=page-description-${lang}]`).type(`description ${lang}`, { force: true });
//       cy.get(`[data-e2e=page-inline-css-${lang}]`).type(`css ${lang}`);
//       cy.get(`[data-e2e=page-order-by-${lang}]`).type(5);
//     });

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('update page by save', () => {
//     cy.get('#page_table tbody tr a.edit').first().click();

//     cy.get('#pills-tab li').each((element) => {
//       const lang = element.attr('data-id');
//       cy.wait(500);
//       cy.get(`#${lang}-tab`).click();
//     });

//     cy.wait(1000);
//     cy.get('button[name="save_and_update"]').click();
//     cy.wait(1000);
//     cy.get('#go-back').click();
//   });
// });
