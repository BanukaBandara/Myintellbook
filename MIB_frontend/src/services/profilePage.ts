
import { useUserProfile } from '@/stores/User/userProfile';

const userProfile = useUserProfile();

export const Experiance = async()=>{
    return await userProfile.getExperiance();
}

export const Education = async() =>{
    return await userProfile.getEducationInfo();
}
