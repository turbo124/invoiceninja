describe('Test Login Page', () => {

    it('Shows the login page.', () => {
        
        cy.visit('/client/login');
        cy.contains('Client Portal');

        cy.get('input[name=email]').type('cypress@example.com');
        cy.get('input[name=password]').type('password{enter}');
        cy.url().should('include', '/invoices');

        cy.get('.button-link').each(link => {

            if (link.prop('href'))

                cy.visit(link.prop('href'))
                cy.log(link.prop('href'))
                // cy.wait(5000)

                cy.waitUntil(function () {
                    return cy.get('.loader').should('not.exist').then(() => true);
                })


        })

        cy.visit('/client/recurring_invoices').contains('Recurring Invoices');
        cy.visit('/client/payments').contains('Payments');
        cy.visit('/client/quotes').contains('Quotes');

        cy.get('.button-link').each(link => {

            if (link.prop('href'))

                cy.visit(link.prop('href'))
            cy.log(link.prop('href'))
            cy.waitUntil(function () {
                return cy.get('.loader').should('not.exist').then(() => true);
            })

        })

        cy.visit('/client/credits').contains('Credits');


        cy.get('.button-link').each(link => {

            if (link.prop('href'))

            cy.visit(link.prop('href'))
            cy.log(link.prop('href'))
            cy.waitUntil(function () {
                return cy.get('.loader').should('not.exist').then(() => true);
            })

        })

        cy.visit('/client/payment_methods').contains('Payment Methods');
        cy.visit('/client/documents').contains('Documents');
        cy.visit('/client/statement').contains('Statement');
        cy.visit('/client/subscriptions').contains('Subscriptions');

        cy.get('[data-ref="client-profile-dropdown"]').click();
        cy.get('[data-ref="client-profile-dropdown-settings"]').click();
        cy.contains('Client Information');



    });

    it('Shows the vendor login page.', () => {

        cy.visit('/vendors');
        cy.contains('Vendor Portal');

        cy.visit('/vendor/purchase_order/123456');
        cy.visit('/vendor/purchase_orders');



        cy.get('.button-link').each(link => {

            if (link.prop('href'))

                cy.visit(link.prop('href'))
            cy.log(link.prop('href'))
            cy.wait(5000)

        })

    });

    it('Shows the Password Reset Pasge.', () => {


        cy.visit('/client/password/reset');
        cy.contains('Password Recovery');

        cy.get('input[name=email]').type('cypress@example.com{enter}');
        cy.contains('We have e-mailed your password reset link!');

        cy.visit('/client/password/reset');
        cy.contains('Password Recovery');

        cy.get('input[name=email]').type('nono@example.com{enter}');
        cy.contains("We can't find a user with that e-mail address.");

    });

});