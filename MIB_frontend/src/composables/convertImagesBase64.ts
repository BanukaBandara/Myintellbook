
export const imageConverter = () =>
{
  const imageToBase64 = async(image:any) =>{
      const response = await fetch(image);
      const blob = await response.blob();

      const reader = new FileReader();
      reader.onloadend = async() => {
      
          return reader.result;
        // Base64 string with MIME prefix
      };
      
   return reader.readAsDataURL(blob);
  }

  return{
    imageToBase64
  }
}
