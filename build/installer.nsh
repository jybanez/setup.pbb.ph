!macro customInstall
  CreateDirectory "$SMPROGRAMS\Project Bantay Bayan"
  CreateShortCut "$SMPROGRAMS\Project Bantay Bayan\Setup.lnk" "$INSTDIR\Project Bantay Bayan.exe" "--mode setup" "$INSTDIR\Project Bantay Bayan.exe" 0
  CreateShortCut "$SMPROGRAMS\Project Bantay Bayan\Data Prep.lnk" "$INSTDIR\Project Bantay Bayan.exe" "--mode data-prep" "$INSTDIR\Project Bantay Bayan.exe" 0
!macroend

!macro customUnInstall
  Delete "$SMPROGRAMS\Project Bantay Bayan\Setup.lnk"
  Delete "$SMPROGRAMS\Project Bantay Bayan\Data Prep.lnk"
  RMDir "$SMPROGRAMS\Project Bantay Bayan"
!macroend
