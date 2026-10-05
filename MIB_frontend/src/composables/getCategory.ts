import { useUserProfile } from '@/stores/User/userProfile';

const userProfile = useUserProfile();

export const getCategory = () => {
    const CategoriesList = async() => {
        let result = await  userProfile.getCategories();
        return result;
    };

    const getProfessions = async(category: number) => {
        userProfile.category = category;
        let result = await  userProfile.getProfessions();
        return result;
    };

    return {
        CategoriesList,
        getProfessions
    }
};