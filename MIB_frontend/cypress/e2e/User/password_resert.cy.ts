describe('reset password', () => {
    it('should reset password successfully', () => {
        cy.visit('/password/reset'); // Visit the password reset page
        cy.wait(2000);
        cy.get('#userEmail').type('chamath.rmc@gmail.com');
        cy.get("#passwordReset").click();
        cy.wait(3000);
        cy.get('.swal2-confirm').click();
        // Fill in the password reset form      
    });
});