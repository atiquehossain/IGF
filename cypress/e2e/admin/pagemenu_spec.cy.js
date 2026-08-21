// /* eslint-disable no-undef */
// describe('Pagemenu', () => {
//   // cy.refreshDatabase();
//   before(() => {
//     // cy.seed();
//   });

//   beforeEach(() => {
//     cy.loginWithUsername();
//     cy.visit('/admin/page-menu');
//   });

//   it('create new page-menu by save', () => {
//     cy.visit('/admin/page-menu/create');
//     cy.get('#pills-tab li').each((element) => {
//       const lang = element.attr('data-id');
//       cy.get(`#${lang}-tab`).click();
//       cy.get(`[data-e2e=page-menu-type-${lang}]`).select('main');
//       cy.get(`[data-e2e=page-menu-name-${lang}]`).type('pagemenu name');
//       cy.get(`[data-e2e=page-menu-link-${lang}]`).select('frontend.home');
//       cy.get(`[data-e2e=page-menu-order-by-${lang}]`).type(5);
//     });

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('create new page-menu by save and update', () => {
//     cy.visit('/admin/page-menu/create');
//     cy.get('#pills-tab li').each((element) => {
//       const lang = element.attr('data-id');
//       cy.get(`#${lang}-tab`).click();
//       cy.get(`[data-e2e=page-menu-type-${lang}]`).select('main');
//       cy.get(`[data-e2e=page-menu-name-${lang}]`).type('pagemenu name');
//       cy.get(`[data-e2e=page-menu-link-${lang}]`).select('frontend.home');
//       cy.get(`[data-e2e=page-menu-order-by-${lang}]`).type(5);
//     });

//     cy.wait(1000);
//     cy.get('button[name="save_and_update"]').click();
//     cy.wait(1000);

//     cy.get('#pills-tab li').each((element) => {
//       const lang = element.attr('data-id');
//       cy.get(`#${lang}-tab`).click();
//       cy.get(`[data-e2e=page-menu-type-${lang}]`).select('main');
//       cy.get(`[data-e2e=page-menu-name-${lang}]`).type('pagemenu name');
//       cy.get(`[data-e2e=page-menu-link-${lang}]`).select('frontend.home');
//       cy.get(`[data-e2e=page-menu-order-by-${lang}]`).type(5);
//     });

//     cy.wait(1000);
//     cy.get('button[name="save"]').click();
//     cy.wait(1000);
//   });

//   it('update page-menu by save', () => {
//     cy.get('#pageMenu_table tbody tr a.edit').first().click();

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
