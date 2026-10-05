describe('add new password',()=>{
    it('should set new password',()=>{
        cy.visit('/password/reset/:token'); // Visit the password reset page
        cy.wait(2000);
        cy.get('#userPassword').focus().type('7710578@Cha').blur();
        cy.get('body').click(0, 0);
        cy.get('#userconfirmPassword').type('7710578@Cha');
        cy.get('#showPassword').click();
        cy.wait(1500);
        cy.get('#showPassword').click();
        cy.get('#showConfirmPassword').click();
        cy.wait(1500);
        cy.get('#showConfirmPassword').click();
        // Submit the form
        cy.get('#changePassword').click();
        cy.wait(3000);
        cy.get('.swal2-confirm').click();
        cy.wait(2000);

    });
});