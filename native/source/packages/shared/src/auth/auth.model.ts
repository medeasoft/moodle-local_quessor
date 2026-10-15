export interface LoginResponseDto {
  accessToken: string;
  refreshToken: string;
}

export interface AccessTokenDto {
  accessToken: string;
  refreshToken: string;
}

export interface MoodleSsoResponseDto extends AccessTokenDto {
  redirectPath: string;
}

export interface RefreshTokenDto {
  refreshToken: string;
}
