describe('user details add form',()=>{
    it('should add user basic details',()=>{
        cy.visit('/basicDetails-fill');
        cy.wait(2000);
        cy.get('#first_name').focus().type('John').blur();
        cy.get('#last_name').focus().type('Doe').blur();
        cy.get('#gender').select('1');
        cy.get('#category').select('1');
        cy.get('#profession').select('1');
        cy.wait(1500);
        // Submit the form
        cy.get('#submitDetails').click();
        cy.wait(3000);
        cy.get('.swal2-confirm').click();
        cy.wait(2000);

    })

})